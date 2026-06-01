<?php

namespace Services;

use Data\Database;
use PDO;

/**
 * Manages authentication: registration, login, logout.
 *  Uses the PostgreSQL database via the Singleton Database.
 */
class AuthService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    //login

    /**
     * @return array{success: bool, error?: string}
     */
    public function login(string $email, string $password): array
    {
        if ($email === '' || $password === '') {
            return ['success' => false, 'error' => 'Veuillez remplir tous les champs.'];
        }

        // search user
        $stmt = $this->pdo->prepare('
            SELECT id, email, password_hash, first_name, last_name, is_active
            FROM users
            WHERE email = :email
        ');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'error' => 'Identifiants incorrects.'];
        }

        if (!$user['is_active']) {
            return ['success' => false, 'error' => 'Ce compte a été désactivé.'];
        }

        // Verify pwd
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Identifiants incorrects.'];
        }

        // Dertermine role
        $roles = $this->getUserRoles((int) $user['id']);

        //Update last_login_at
        $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute(['id' => $user['id']]);

        //Create session
        $this->createSession($user, $roles);

        return ['success' => true];
    }
    //register

    /**
     * @param array{email: string, password: string, password_confirm: string,
     *              first_name: string, last_name: string, rgpd_consent: bool} $data
     * @return array{success: bool, error?: string}
     */
    public function register(array $data): array
    {
        $email           = trim($data['email'] ?? '');
        $password        = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';
        $firstName       = trim($data['first_name'] ?? '');
        $lastName        = trim($data['last_name'] ?? '');
        $rgpdConsent     = (bool) ($data['rgpd_consent'] ?? false);

        if ($email === '' || $password === '' || $firstName === '' || $lastName === '') {
            return ['success' => false, 'error' => 'Tous les champs sont obligatoires.'];
        }

        if (mb_strlen($password) < 8) {
            return ['success' => false, 'error' => 'Le mot de passe doit contenir au moins 8 caractères.'];
        }

        if ($password !== $passwordConfirm) {
            return ['success' => false, 'error' => 'Les mots de passe ne correspondent pas.'];
        }

        if (!$rgpdConsent) {
            return ['success' => false, 'error' => 'Vous devez accepter le traitement des données.'];
        }

        // Check the email domain
        $domain = $this->extractDomain($email);
        $domainConfig = $this->getDomainConfig($domain);

        if (!$domainConfig) {
            return ['success' => false, 'error' => 'Seules les adresses @etu.univ-amu.fr et @univ-amu.fr sont acceptées.'];
        }

        // Check that the email address is not already in use.
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);

        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Un compte existe déjà avec cette adresse.'];
        }

        // insert username
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare('
            INSERT INTO users (email, password_hash, first_name, last_name, consent_at, consent_version)
            VALUES (:email, :hash, :first_name, :last_name, NOW(), :consent_version)
            RETURNING id
        ');
        $stmt->execute([
            'email'           => $email,
            'hash'            => $hash,
            'first_name'      => $firstName,
            'last_name'       => $lastName,
            'consent_version' => 'v1',
        ]);

        $userId = (int) $stmt->fetchColumn();

        // Insert into the role table
        $role = $domainConfig['role']; // 'STUDENT' ou 'TEACHER'

        if ($role === 'STUDENT') {
            $this->pdo->prepare('INSERT INTO students (id) VALUES (:id)')
                ->execute(['id' => $userId]);
        } elseif ($role === 'TEACHER') {
            $this->pdo->prepare('INSERT INTO teachers (id) VALUES (:id)')
                ->execute(['id' => $userId]);
        }

        return ['success' => true];
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Extracts the domain from an email address.
     *  e.g., "thomas.dupont@etu.univ-amu.fr" → "etu.univ-amu.fr"
     */
    private function extractDomain(string $email): string
    {
        $parts = explode('@', $email);
        return strtolower(end($parts));
    }

    /**
     * Searches for the domain configuration in email_domain_configs.
     *  Returns null if the domain is not authorized.
     */
    private function getDomainConfig(string $domain): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT domain, role
            FROM email_domain_configs
            WHERE domain = :domain AND is_active = TRUE
        ');
        $stmt->execute(['domain' => $domain]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Determines a user's roles by checking
     *  each role table (students, teachers, researchers, department_administrators).
     *
     * @return string[]  ex: ['STUDENT'] ou ['TEACHER', 'DEPARTMENT_ADMIN']
     */
    private function getUserRoles(int $userId): array
    {
        $roles = [];

        $tables = [
            'students'                  => 'STUDENT',
            'teachers'                  => 'TEACHER',
            'researchers'               => 'RESEARCHER',
            'department_administrators' => 'DEPARTMENT_ADMIN',
        ];

        foreach ($tables as $table => $role) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            if ($stmt->fetch()) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * Creates the PHP session with the user's data.
     */
    private function createSession(array $user, array $roles): void
    {
        // Regenerate the session ID to avoid session fixation
        session_regenerate_id(true);

        $_SESSION['user_id']         = (int) $user['id'];
        $_SESSION['user_email']      = $user['email'];
        $_SESSION['user_first_name'] = $user['first_name'];
        $_SESSION['user_last_name']  = $user['last_name'];
        $_SESSION['roles']           = $roles;
        $_SESSION['token']           = bin2hex(random_bytes(32));
    }
}