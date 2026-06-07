<?php

declare(strict_types=1);

namespace Services;

/**
 * Sends emails via raw SMTP sockets (no framework).
 * Compatible with Mailpit in dev, any SMTP server in prod.
 */
final class MailService
{
    private string $host;
    private int $port;
    private string $from;
    private string $user;
    private string $pass;
    private string $encryption;

    public function __construct()
    {
        $config = require __DIR__ . '/../Config/config.php';
        $this->host = $config['mail']['host'];
        $this->port = $config['mail']['port'];
        $this->from = $config['mail']['from'];
        $this->user = (string)($config['mail']['user'] ?? '');
        $this->pass = (string)($config['mail']['pass'] ?? '');
        $this->encryption = (string)($config['mail']['encryption'] ?? '');
    }

    public function send(string $to, string $subject, string $htmlBody): bool
    {
        // Implicit TLS wraps the socket from the first byte (port 465 style);
        // plain and STARTTLS both start as a clear connection.
        $transport = $this->encryption === 'ssl'
            ? "ssl://{$this->host}"
            : $this->host;

        $socket = @fsockopen($transport, $this->port, $errno, $errstr, 10);
        if (!$socket) {
            error_log("MailService: connexion SMTP échouée — {$errstr} ({$errno})");
            return false;
        }
        stream_set_timeout($socket, 10);

        try {
            $this->expect($this->read($socket), '220');
            $this->ehlo($socket);

            // Upgrade a clear connection to TLS, then re-EHLO as required.
            if ($this->encryption === 'tls') {
                $this->expect($this->cmd($socket, 'STARTTLS'), '220');
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('échec de la négociation STARTTLS');
                }
                $this->ehlo($socket);
            }

            // Authenticate only when credentials are configured (prod).
            if ($this->user !== '') {
                $this->expect($this->cmd($socket, 'AUTH LOGIN'), '334');
                $this->expect($this->cmd($socket, base64_encode($this->user)), '334');
                $this->expect($this->cmd($socket, base64_encode($this->pass)), '235');
            }

            $this->expect($this->cmd($socket, "MAIL FROM:<{$this->from}>"), '250');
            $this->expect($this->cmd($socket, "RCPT TO:<{$to}>"), '250');
            $this->expect($this->cmd($socket, 'DATA'), '354');

            $headers = "From: I-AMU <{$this->from}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "\r\n";

            // Dot-stuffing: a body line starting with '.' would otherwise be
            // read as the end-of-data marker.
            $body = str_replace("\r\n.", "\r\n..", $htmlBody);

            $this->write($socket, $headers . $body . "\r\n.\r\n");
            $this->expect($this->read($socket), '250');

            $this->write($socket, "QUIT\r\n");
        } catch (RuntimeException $e) {
            error_log('MailService: ' . $e->getMessage());
            fclose($socket);
            return false;
        }

        fclose($socket);
        return true;
    }
}