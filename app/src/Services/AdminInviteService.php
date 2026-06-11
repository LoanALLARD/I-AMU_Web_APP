<?php

declare(strict_types=1);

namespace Services;

use Models\UserRepository;
use Models\SuperAdministratorRepository;
use PDO;

/**
 * Admin invitation by signed link (stateless, no DB row).
 * The token carries email + department + role + expiry, signed with the app
 * secret. The role is granted only when the invitee submits the acceptance
 * form. Two roles are supported: a department administrator (scoped to a
 * department) and a super administrator (no department).
 */
final class AdminInviteService
{
    private const TTL_SECONDS = 7 * 24 * 3600;

    public const ROLE_DEPARTMENT_ADMIN = 'DEPT_ADMIN';
    public const ROLE_SUPER_ADMIN      = 'SUPER_ADMIN';

    private UserRepository $users;
    private SuperAdministratorRepository $superAdmins;
    private string $secret;

    public function __construct(PDO $pdo)
    {
        $config = require __DIR__ . '/../Config/config.php';
        $this->users = new UserRepository($pdo);
        $this->superAdmins = new SuperAdministratorRepository($pdo);
        $this->secret = (string) ($config['app']['secret'] ?? '');
    }

    /**
     * Builds a signed token: base64url(email|deptId|role|expiresAt).signature
     */
    public function makeToken(string $email, int $departmentId, string $role = self::ROLE_DEPARTMENT_ADMIN): string {
        $expiresAt = time() + self::TTL_SECONDS;
        $payload   = $email . '|' . $departmentId . '|' . $role . '|' . $expiresAt;
        $encoded   = $this->base64UrlEncode($payload);
        $signature = $this->sign($encoded);

        return $encoded . '.' . $signature;
    }

    /**
     * Convenience builder for a super admin invitation (no department).
     */
    public function makeSuperAdminToken(string $email): string
    {
        return $this->makeToken($email, 0, self::ROLE_SUPER_ADMIN);
    }

    /**
     * Validates a token and returns its data, or null if invalid/expired. Older 3-field tokens
     * (without an explicit role) are read as department admin invitations for backward compatibility.
     *
     * @return array{email:string, department_id:int, role:string}|null
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

        if (count($fields) === 4) {
            [$email, $departmentId, $role, $expiresAt] = $fields;
        } elseif (count($fields) === 3) {
            [$email, $departmentId, $expiresAt] = $fields;
            $role = self::ROLE_DEPARTMENT_ADMIN;
        } else {
            return null;
        }

        if ((int) $expiresAt < time()) {
            return null;
        }

        return ['email' => $email, 'department_id' => (int) $departmentId, 'role' => $role,];
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
        $passwordError = \Core\PasswordPolicy::validate($password);
        if ($passwordError !== null) {
            return ['success' => false, 'error' => $passwordError];
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

    /**
     * Creates a super admin account from a verified super admin token plus the
     * form fields. Super admins live in their own table, not in `users`.
     *
     * @return array{success:true, super_admin_id:int}|array{success:false, error:string}
     */
    public function acceptSuperAdmin(string $token, string $password, string $passwordConfirm, string $firstName, string $lastName):
        array {
        $data = $this->verifyToken($token);
        if ($data === null || $data['role'] !== self::ROLE_SUPER_ADMIN) {
            return ['success' => false, 'error' => 'Lien invalide ou expiré.'];
        }

        $firstName = trim($firstName);
        $lastName  = trim($lastName);

        if ($firstName === '' || $lastName === '' || $password === '') {
            return ['success' => false, 'error' => 'Tous les champs sont obligatoires.'];
        }
        $passwordError = \Core\PasswordPolicy::validate($password);
        if ($passwordError !== null) {
            return ['success' => false, 'error' => $passwordError];
        }
        if ($password !== $passwordConfirm) {
            return ['success' => false, 'error' => 'Les mots de passe ne correspondent pas.'];
        }
        if ($this->superAdmins->emailExists($data['email']) || $this->users->emailExists($data['email'])) {
            return ['success' => false, 'error' => 'Un compte existe déjà pour cette adresse.'];
        }

        try {
            $superAdminId = $this->superAdmins->create(
                $data['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $firstName,
                $lastName
            );
        } catch (\Throwable $e) {
            error_log('SUPER ADMIN INVITE ACCEPT ERROR: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors de la création du compte.'];
        }

        return ['success' => true, 'super_admin_id' => $superAdminId];
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