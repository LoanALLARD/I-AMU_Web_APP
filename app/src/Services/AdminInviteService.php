<?php

declare(strict_types=1);

namespace Services;

use Models\UserRepository;
use PDO;

/**
 * Department-admin invitation by signed link (stateless, no DB row).
 * The token carries email + department + expiry, signed with the app secret.
 * The role is granted only when the invitee submits the acceptance form.
 */
final class AdminInviteService
{
    private const TTL_SECONDS = 7 * 24 * 3600;

    private UserRepository $users;
    private string $secret;

    public function __construct(PDO $pdo)
    {
        $config       = require __DIR__ . '/../Config/config.php';
        $this->users  = new UserRepository($pdo);
        $this->secret = (string) ($config['app']['secret'] ?? '');
    }

    /**
     * Builds a signed token: base64url(email|deptId|expiresAt).signature
     */
    public function makeToken(string $email, int $departmentId): string
    {
        $expiresAt = time() + self::TTL_SECONDS;
        $payload   = $email . '|' . $departmentId . '|' . $expiresAt;
        $encoded   = $this->base64UrlEncode($payload);
        $signature = $this->sign($encoded);

        return $encoded . '.' . $signature;
    }

    /**
     * Validates a token and returns its data, or null if invalid/expired.
     *
     * @return array{email:string, department_id:int}|null
     */
    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$encoded, $signature] = $parts;

        // Constant-time comparison against a forged signature.
        if (!hash_equals($this->sign($encoded), $signature)) {
            return null;
        }

        $payload = $this->base64UrlDecode($encoded);
        $fields  = explode('|', $payload);
        if (count($fields) !== 3) {
            return null;
        }
        [$email, $departmentId, $expiresAt] = $fields;

        if ((int) $expiresAt < time()) {
            return null;
        }

        return ['email' => $email, 'department_id' => (int) $departmentId];
    }

    /**
     * Creates the admin account from a verified token + the form fields.
     *
     * @return array{success:true, user_id:int}|array{success:false, error:string}
     */
    public function accept(
        string $token,
        string $password,
        string $passwordConfirm,
        string $firstName,
        string $lastName,
        int $invitedById
    ): array {
        $data = $this->verifyToken($token);
        if ($data === null) {
            return ['success' => false, 'error' => 'Lien invalide ou expiré.'];
        }

        $firstName = trim($firstName);
        $lastName  = trim($lastName);

        if ($firstName === '' || $lastName === '' || $password === '') {
            return ['success' => false, 'error' => 'Tous les champs sont obligatoires.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Le mot de passe doit faire au moins 8 caractères.'];
        }
        if ($password !== $passwordConfirm) {
            return ['success' => false, 'error' => 'Les mots de passe ne correspondent pas.'];
        }
        if ($this->users->emailExists($data['email'])) {
            return ['success' => false, 'error' => 'Un compte existe déjà pour cette adresse.'];
        }

        try {
            $userId = $this->users->createDepartmentAdmin(
                [
                    'email'         => $data['email'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'first_name'    => $firstName,
                    'last_name'     => $lastName,
                    'department_id' => $data['department_id'],
                ],
                $invitedById
            );
        } catch (\Throwable $e) {
            error_log('ADMIN INVITE ACCEPT ERROR: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors de la création du compte.'];
        }

        return ['success' => true, 'user_id' => $userId];
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
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