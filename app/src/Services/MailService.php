<?php

declare(strict_types=1);

namespace Services;

/**
 * Sends emails via raw SMTP sockets (no framework).
 * Supports optional STARTTLS and AUTH LOGIN so the same code works with
 * Mailpit in dev (no auth, no TLS) and a real SMTP relay in prod.
 */
final class MailService
{
    private string $host;
    private int $port;
    private string $from;
    private ?string $username;
    private ?string $password;
    private string $encryption; // 'tls' (STARTTLS), 'ssl' (implicit), or 'none' for AlwaysData

    public function __construct()
    {
        $config = require __DIR__ . '/../Config/config.php';
        $this->host = $config['mail']['host'];
        $this->port = $config['mail']['port'];
        $this->from = $config['mail']['from'];
        $this->username = $config['mail']['username'] ?? null;
        $this->password = $config['mail']['password'] ?? null;
        $this->encryption = $config['mail']['encryption'] ?? 'none';
    }

    public function send(string $to, string $subject, string $htmlBody): bool
    {
        // Implicit TLS (port 465) wraps the socket from the start.
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host;

        $socket = @fsockopen($remote, $this->port, $errno, $errstr, 10);
        if (!$socket) {
            error_log("MailService: connexion SMTP echouee - {$errstr} ({$errno})");
            return false;
        }

        try {
            $this->expect($socket, 220);
            $this->ehlo($socket);

            // STARTTLS (port 587): upgrade the plain connection to TLS.
            if ($this->encryption === 'tls') {
                $this->command($socket, "STARTTLS", 220);
                if (!stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                )) {
                    error_log('MailService: echec du passage en TLS');
                    return false;
                }
                // Re-EHLO over the encrypted channel (required by RFC).
                $this->ehlo($socket);
            }

            // Authentication (skipped when no credentials, e.g. Mailpit).
            if ($this->username !== null && $this->password !== null) {
                $this->command($socket, "AUTH LOGIN", 334);
                $this->command($socket, base64_encode($this->username), 334);
                $this->command($socket, base64_encode($this->password), 235);
            }

            $this->command($socket, "MAIL FROM:<{$this->from}>", 250);
            $this->command($socket, "RCPT TO:<{$to}>", 250);
            $this->command($socket, "DATA", 354);

            $messageId = sprintf('<%s@%s>', bin2hex(random_bytes(16)), 'alwaysdata.net');

            $headers  = "From: I-AMU <{$this->from}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "Message-ID: {$messageId}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "\r\n";

            // Dot-stuffing: a line starting with '.' must be doubled.
            $body = preg_replace('/^\./m', '..', $htmlBody);

            $this->command($socket, $headers . $body . "\r\n.", 250);
            $this->command($socket, "QUIT", 221);
        } catch (\RuntimeException $e) {
            error_log('MailService: ' . $e->getMessage());
            fclose($socket);
            return false;
        }

        fclose($socket);
        return true;
    }

    /** @param resource $socket */
    private function ehlo($socket): void
    {
        $this->command($socket, "EHLO i-amu", 250);
    }

    /**
     * Sends a command and asserts the reply code.
     * @param resource $socket
     */
    private function command($socket, string $line, int $expected): void
    {
        fwrite($socket, $line . "\r\n");
        $this->expect($socket, $expected);
    }

    /**
     * Reads a (possibly multi-line) SMTP reply and checks its code.
     * @param resource $socket
     */
    private function expect($socket, int $expected): void
    {
        $response = '';
        while ($buf = fgets($socket, 515)) {
            $response .= $buf;
            // A space at position 3 marks the final line of the reply.
            if (isset($buf[3]) && $buf[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if ($code !== $expected) {
            throw new \RuntimeException("attendu {$expected}, recu : " . trim($response));
        }
    }
}