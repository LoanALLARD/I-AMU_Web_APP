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
            SELECT id, email, password_hash, first_name, last_name, is_active, email_verified_at
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
        // Verify mail confirmed
        if ($user['email_verified_at'] === null) {
            return ['success' => false, 'error' => 'Votre adresse e-mail n\'a pas encore été vérifiée. Consultez votre mail.'];
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
    //verify mail
    /**
     * @return array{success: bool, error?: string}
     */
    public function verifyEmail(string $token): array
    {
        if ($token === '') {
            return ['success' => false, 'error' => 'Lien de vérification invalide.'];
        }

        $stmt = $this->pdo->prepare('
            SELECT id, email_verified_at 
            FROM users 
            WHERE email_verify_token = :token
        ');
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();
        if (!$user) {
            return ['success' => false, 'error' => 'Lien de vérification invalide ou expiré.'];
        }

        if ($user['email_verified_at'] !== null) {
            return ['success' => true];
        }

        $stmt = $this->pdo->prepare('
            UPDATE users 
            SET email_verified_at = NOW(), email_verify_token = NULL 
            WHERE id = :id
        ');
        $stmt->execute(['id' => $user['id']]);
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
        $stmt = $this->pdo->prepare('SELECT id 
                                            FROM users 
                                            WHERE email = :email');
        $stmt->execute(['email' => $email]);

        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Un compte existe déjà avec cette adresse.'];
        }

        $verifyToken = bin2hex(random_bytes(32));
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // insert username
        $stmt = $this->pdo->prepare('
            INSERT INTO users (email, password_hash, first_name, last_name, consent_at, consent_version, email_verify_token)
            VALUES (:email, :hash, :first_name, :last_name, NOW(), :consent_version, :token)
            RETURNING id
        ');
        $stmt->execute([
            'email'           => $email,
            'hash'            => $hash,
            'first_name'      => $firstName,
            'last_name'       => $lastName,
            'consent_version' => 'v1',
            'token'           => $verifyToken,
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

        $this->sendVerificationEmail($email, $firstName, $verifyToken);
        return ['success' => true];
    }

    //Verification
     /**
     * @return array{success: bool, error?: string}
     */
    public function resendVerification(string $email): array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, first_name, email_verified_at 
            FROM users WHERE email = :email
        ');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || $user['email_verified_at'] !== null) {
            return ['success' => true];
        }

        $newToken = bin2hex(random_bytes(32));

        $this->pdo->prepare('UPDATE users SET email_verify_token = :token WHERE id = :id')
            ->execute(['token' => $newToken, 'id' => $user['id']]);

        $this->sendVerificationEmail($email, $user['first_name'], $newToken);

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

    /**
     * send the vérification mail with activation link.
     */
    private function sendVerificationEmail(string $email, string $firstName, string $token): void
    {
        $baseUrl = ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'https' : 'http';
        $baseUrl .= '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8085');
        $verifyUrl = $baseUrl . '/verify?token=' . $token;

        $subject = '=?UTF-8?B?' . base64_encode('I-AMU — Vérifiez votre adresse e-mail') . '?=';

        $body = "Bonjour {$firstName},\r\n\r\n"
            . "Merci de vous être inscrit(e) sur la plateforme I-AMU.\r\n\r\n"
            . "Pour activer votre compte, cliquez sur le lien suivant :\r\n"
            . "{$verifyUrl}\r\n\r\n"
            . "Si vous n'êtes pas à l'origine de cette inscription, ignorez ce message.\r\n\r\n"
            . "Cordialement,\r\n"
            . "L'équipe I-AMU — IUT Informatique, Aix-Marseille Université";

        $headers = "From: noreply@univ-amu.fr\r\n"
            . "Reply-To: noreply@univ-amu.fr\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "X-Mailer: I-AMU/1.0";

        mail($email, $subject, $body, $headers);
    }

}