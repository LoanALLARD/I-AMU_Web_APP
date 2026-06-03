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
    private int    $port;
    private string $from;

    public function __construct()
    {
        $config     = require __DIR__ . '/../Config/config.php';
        $this->host = $config['mail']['host'];
        $this->port = $config['mail']['port'];
        $this->from = $config['mail']['from'];
    }

    public function send(string $to, string $subject, string $htmlBody): bool
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
        if (!$socket) {
            error_log("MailService: connexion SMTP échouée — {$errstr} ({$errno})");
            return false;
        }

        $this->read($socket);
        $this->write($socket, "EHLO localhost\r\n");
        $this->read($socket);
        $this->write($socket, "MAIL FROM:<{$this->from}>\r\n");
        $this->read($socket);
        $this->write($socket, "RCPT TO:<{$to}>\r\n");
        $this->read($socket);
        $this->write($socket, "DATA\r\n");
        $this->read($socket);

        $headers  = "From: I-AMU <{$this->from}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "\r\n";

        $this->write($socket, $headers . $htmlBody . "\r\n.\r\n");
        $this->read($socket);
        $this->write($socket, "QUIT\r\n");

        fclose($socket);
        return true;
    }

    private function write($socket, string $data): void
    {
        fwrite($socket, $data);
    }

    private function read($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }
}