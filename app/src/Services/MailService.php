<?php

declare(strict_types=1);

namespace Services;

use RuntimeException;

/**
 * Sends emails via raw SMTP sockets (no framework).
 * Supports plain SMTP (Mailpit in dev) and authenticated SMTP with
 * STARTTLS or implicit TLS (any real provider in prod, e.g. AlwaysData).
 */
final class MailService
{
    private string $host;
    private int    $port;
    private string $from;
    private ?string $username;
    private ?string $password;
    // 'none' (dev), 'tls' (STARTTLS, port 587), or 'ssl' (implicit TLS, port 465).
    private string $encryption;

    public function __construct()
    {
        $config           = require __DIR__ . '/../Config/config.php';
        $this->host       = $config['mail']['host'];
        $this->port       = $config['mail']['port'];
        $this->from       = $config['mail']['from'];
        $this->username   = $config['mail']['username'] ?? null;
        $this->password   = $config['mail']['password'] ?? null;
        $this->encryption = $config['mail']['encryption'] ?? 'none';
    }

    public function send(string $to, string $subject, string $htmlBody): bool
    {
        // Implicit TLS (port 465) wraps the whole connection from the start.
        $remote = $this->encryption === 'ssl'
            ? "ssl://{$this->host}:{$this->port}"
            : "{$this->host}:{$this->port}";

        $socket = @stream_socket_client($remote, $errno, $errstr, 10);
        if (!$socket) {
            error_log("MailService: SMTP connection failed - {$errstr} ({$errno})");
            return false;
        }

        try {
            $this->expect($socket, 220);

            $this->command($socket, "EHLO localhost", 250);

            // STARTTLS (port 587): upgrade the plain connection to TLS, then
            // re-issue EHLO over the secured channel as required by the RFC.
            if ($this->encryption === 'tls') {
                $this->command($socket, "STARTTLS", 220);
                $ok = stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                );
                if ($ok !== true) {
                    throw new RuntimeException('STARTTLS negotiation failed.');
                }
                $this->command($socket, "EHLO localhost", 250);
            }

            // Authenticate when credentials are configured (prod). Skipped in
            // dev against Mailpit, which accepts mail without auth.
            if ($this->username !== null && $this->password !== null) {
                $this->command($socket, "AUTH LOGIN", 334);
                $this->command($socket, base64_encode($this->username), 334);
                $this->command($socket, base64_encode($this->password), 235);
            }

            $this->command($socket, "MAIL FROM:<{$this->from}>", 250);
            $this->command($socket, "RCPT TO:<{$to}>", 250);
            $this->command($socket, "DATA", 354);

            $headers  = "From: I-AMU <{$this->from}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "\r\n";

            // End-of-data marker is a lone dot on its own line.
            $this->command($socket, $headers . $htmlBody . "\r\n.", 250);
            $this->write($socket, "QUIT\r\n");

            return true;
        } catch (RuntimeException $e) {
            error_log('MailService: ' . $e->getMessage());
            return false;
        } finally {
            fclose($socket);
        }
    }

    /**
     * Sends a command and asserts the reply starts with the expected code.
     *
     * @param resource $socket
     */
    private function command($socket, string $data, int $expectedCode): void
    {
        $this->write($socket, $data . "\r\n");
        $this->expect($socket, $expectedCode);
    }

    /**
     * Reads a reply and throws when the SMTP status code does not match.
     *
     * @param resource $socket
     */
    private function expect($socket, int $expectedCode): void
    {
        $response = $this->read($socket);
        $code     = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new RuntimeException(
                "Unexpected SMTP reply: expected {$expectedCode}, got " . trim($response)
            );
        }
    }

    /**
     * @param resource $socket
     */
    private function write($socket, string $data): void
    {
        fwrite($socket, $data);
    }

    /**
     * Reads a full SMTP reply, handling multi-line responses (a line with a
     * hyphen after the code continues; a space after the code ends it).
     *
     * @param resource $socket
     */
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