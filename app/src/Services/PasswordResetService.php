<?php

declare(strict_types=1);

namespace Services;

use Core\PasswordPolicy;
use Models\UserRepository;
use PDO;

/**
 * Password reset by signed link (stateless, no DB row).
 *
 * Mirrors AdminInviteService: the token carries the email + an expiry, signed
 * with the app secret. The signature also folds in the account's current
 * password hash, so the link becomes invalid the moment the password changes —
 * giving single-use semantics for free, with no schema column to store.
 *
 * Scope: only `users` rows are reachable here (via UserRepository). Super
 * administrators live in their own table and have a dedicated login, so they
 * are excluded by construction and never receive a reset link.
 */
final class PasswordResetService
{
    private const TTL_SECONDS = 3600; // 1h — a reset link must be short-lived.

    private UserRepository $users;
    private string $secret;

    public function __construct(PDO $pdo)
    {
        $config       = require __DIR__ . '/../Config/config.php';
        $this->users  = new UserRepository($pdo);
        $this->secret = (string) ($config['app']['secret'] ?? '');
    }

    /**
     * Builds a signed token: base64url(email|expiresAt).signature, where the
     * signature also covers the current password hash so the link dies once
     * the password is changed.
     */
    public function makeToken(string $email, string $passwordHash): string
    {
        $expiresAt = time() + self::TTL_SECONDS;
        $payload   = $email . '|' . $expiresAt;
        $encoded   = $this->base64UrlEncode($payload);
        $signature = $this->sign($encoded, $passwordHash);

        return $encoded . '.' . $signature;
    }

    /**
     * Validates a token and returns its data, or null when invalid, expired,
     * forged, or already consumed (password changed since it was issued).
     *
     * @return array{email: string, user_id: int}|null
     */
    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$encoded, $signature] = $parts;

        $payload = $this->base64UrlDecode($encoded);
        $fields  = explode('|', $payload);
        if (count($fields) !== 2) {
            return null;
        }
        [$email, $expiresAt] = $fields;

        if ((int) $expiresAt < time()) {
            return null;
        }

        // The signature is bound to the current password hash: reload the user
        // and recompute it. A reset already performed (hash changed) no longer
        // matches, so the same link cannot be replayed.
        $row = $this->users->findByEmail($email);
        if ($row === null) {
            return null;
        }

        $expected = $this->sign($encoded, (string) $row['password_hash']);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        return ['email' => $email, 'user_id' => (int) $row['id']];
    }

    /**
     * Sends a reset link for the given email. Always silent about whether the
     * account exists (anti email-enumeration): the caller surfaces the same
     * generic message regardless of the outcome.
     */
    public function requestReset(string $email): void
    {
        $email = trim($email);
        if ($email === '') {
            return;
        }

        $row = $this->users->findByEmail($email);
        if ($row === null) {
            return;
        }

        $token = $this->makeToken($email, (string) $row['password_hash']);

        // Derive the base URL from the current request so the link points to
        // the host the user actually reached the app through.
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        $link   = $scheme . '://' . $host . '/reset-password?token=' . urlencode($token);

        $mail = new MailService();
        $mail->send(
            $email,
            'Reinitialisation de votre mot de passe — I-AMU',
            '<h2>Reinitialisation de mot de passe</h2>'
            . '<p>Vous avez demande a reinitialiser votre mot de passe. '
            . 'Cliquez sur le lien ci-dessous (valable 1 heure) :</p>'
            . '<p><a href="' . htmlspecialchars($link) . '">Choisir un nouveau mot de passe</a></p>'
            . '<p>Si vous n\'etes pas a l\'origine de cette demande, ignorez cet email : '
            . 'votre mot de passe reste inchange.</p>'
        );
    }

    /**
     * Applies a new password from a verified token.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function reset(string $token, string $password, string $passwordConfirm): array
    {
        $data = $this->verifyToken($token);
        if ($data === null) {
            return ['success' => false, 'error' => 'Lien invalide ou expiré.'];
        }

        $passwordError = PasswordPolicy::validate($password);
        if ($passwordError !== null) {
            return ['success' => false, 'error' => $passwordError];
        }
        if ($password !== $passwordConfirm) {
            return ['success' => false, 'error' => 'Les mots de passe ne correspondent pas.'];
        }

        try {
            // updatePassword() hashes internally.
            $this->users->updatePassword($data['user_id'], $password);
        } catch (\Throwable $e) {
            error_log('PASSWORD RESET ERROR: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors de la réinitialisation du mot de passe.'];
        }

        return ['success' => true];
    }

    private function sign(string $encoded, string $passwordHash): string
    {
        return $this->base64UrlEncode(
            hash_hmac('sha256', $encoded . '|' . $passwordHash, $this->secret, true)
        );
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
