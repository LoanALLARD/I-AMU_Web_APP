<?php

declare(strict_types=1);

namespace Services;

use Models\EmailDomainRepository;
use Models\PlaceRepository;
use Models\UserRepository;
use PDO;
use Services\MailService;

/**
 * Authentication service against the real `users` table.
 *
 * Owns the application logic (input validation, domain -> role mapping,
 * password hashing/verification, session population). All SQL lives in
 * the repositories, which this service drives.
 *
 * Schema alignment notes:
 *   - Tables and columns are plural / `id`-based per init-scripts/IAMU_db.sql:
 *     `users.id`, `users.last_login_at`, `teachers.id`, `students.id`.
 *   - `users` no longer carries `gdpr_consent` / `gdpr_consent_at`; the
 *     equivalent is now `consent_at` + `consent_version`. We do not touch
 *     these fields here — they are owned by spec 06 (RGPD).
 */
final class AuthService
{
    private UserRepository $users;
    private PlaceRepository $places;
    private EmailDomainRepository $emailDomains;

    public function __construct(PDO $pdo)
    {
        $this->users        = new UserRepository($pdo);
        $this->places       = new PlaceRepository($pdo);
        $this->emailDomains = new EmailDomainRepository($pdo);
    }

    /**
     * @return array{success: true} | array{success: false, error: string}
     */
    public function login(string $email, string $password): array
    {
        if ($email === '' || $password === '') {
            return ['success' => false, 'error' => 'Email et mot de passe requis.'];
        }

        $row = $this->users->findByEmail($email);

        if ($row === null || !password_verify($password, (string) $row['password_hash'])) {
            return ['success' => false, 'error' => 'Identifiants invalides.'];
        }

        if (!$row['is_active']) {
            return [
                'success' => false,
                'error' => 'Ce compte est désactivé.',
                'deactivated' => true,
                'email' => (string) $row['email']
                ];
        }
        if ($row['email_verified_at'] === null) {
            return [
                'success' => false,
                'error'   => 'Veuillez consultez votre boîte mail.',
            ];
        }

        $userId = (int) $row['id'];

        // Exclusivity with the super admin session (see SPEC-superadmin-auth.md,
        // D3): a normal user login must never leave a super admin identity
        // behind. Drop the dedicated keys before keying as a user.
        unset(
            $_SESSION['super_admin_id'],
            $_SESSION['super_admin_email'],
            $_SESSION['super_admin_first_name'],
            $_SESSION['super_admin_last_name']
        );

        $this->users->touchLastLogin($userId);

        $_SESSION['user_id']         = $userId;
        $_SESSION['user_email']      = (string) $row['email'];
        $_SESSION['user_first_name'] = (string) $row['first_name'];
        $_SESSION['user_last_name']  = (string) $row['last_name'];
        $_SESSION['roles']           = $this->resolveRoles($userId);
        $_SESSION['isSpecialized']   = $this->users->isTeacherSpecialized($userId);
        $_SESSION['department_id']   = $this->users->getDepartmentIdByUserId($userId);
        $_SESSION['user_theme']      = $row['theme'] ?? null;
        $_SESSION['user_department_id'] = $row['department_id'] !== null
            ? (int) $row['department_id']
            : null;

        session_regenerate_id(true);

        return ['success' => true];
    }

    /**
     * Registers a new user.
     *
     * Behaviour:
     *   - Format-validates the form input (email, password >= 8 chars,
     *     password_confirm match, RGPD consent ticked).
     *   - Looks up the email domain in `email_domain_configs` to derive the
     *     role (active domains only). An unknown / disabled domain is
     *     rejected — only domains an admin configured can register.
     *   - Hashes the password with bcrypt.
     *   - Delegates persistence to UserRepository::createUserWithRole(),
     *     which creates the user + role row in a single transaction so a
     *     half-created account can never linger.
     *
     * On success the new user is auto-logged-in (the session is populated
     * via login()), so the caller can redirect straight to the app.
     *
     * @param array<string, mixed> $data
     * @return array{success: true} | array{success: false, error: string}
     */
    public function register(array $data): array
    {
        $email           = trim((string) ($data['email']            ?? ''));
        $password        = (string) ($data['password']             ?? '');
        $passwordConfirm = (string) ($data['password_confirm']     ?? '');
        $firstName       = trim((string) ($data['first_name']      ?? ''));
        $lastName        = trim((string) ($data['last_name']       ?? ''));
        $placeId         = (int) ($data['place_id']                ?? 0);
        $departmentId    = (int) ($data['department_id']           ?? 0);
        $isResearcher    = (bool)  ($data['is_researcher']         ?? false);
        $rgpdConsent     = (bool)  ($data['rgpd_consent']          ?? false);
        $promo_year      = (int) ($data['promo_year']              ?? '');

        // ----- Format validation ------------------------------------
        $validationError = $this->validateRegistration(
            $email,
            $password,
            $passwordConfirm,
            $firstName,
            $lastName,
            $rgpdConsent
        );
        if ($validationError !== null) {
            return ['success' => false, 'error' => $validationError];
        }

        // ----- Email uniqueness -------------------------------------
        if ($this->users->emailExists($email)) {
            return ['success' => false, 'error' => 'Cet email est déjà utilisé.'];
        }

        if ($isResearcher) {
            $registration = $this->prepareResearcherRegistration($email);
        } else {
            $registration = $this->prepareMemberRegistration($email, $placeId, $departmentId);
        }
        if (isset($registration['error'])) {
            return ['success' => false, 'error' => $registration['error']];
        }

        // ----- Insert (user + role row, atomically) -----------------
        try {
            $userId = $this->users->createUserWithRole(
                [
                    'email'           => $email,
                    'password_hash'   => password_hash($password, PASSWORD_DEFAULT),
                    'first_name'      => $firstName,
                    'last_name'       => $lastName,
                    'department_id'   => $registration['department_id'],
                    'laboratory_id'   => $registration['laboratory_id'],
                    'promo_year'      => $promo_year,
                    'consent_version' => $isResearcher ? 'researcher-1.0' : 'member-1.0'

                ],
                $registration['role']
            );
            //  Email verification token
            $token = bin2hex(random_bytes(32));
            $this->users->setVerifyToken($userId, $token);

            // Derive the base URL from the current request so the link points to
            // the host the super admin actually reached the app through.
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost:8085';
            $link   = $scheme . '://' . $host
                . '/admin-invite/accept?token=' . urlencode($token);

            $mail = new MailService();
            $mail->send(
                $email,
                'Vérifiez votre adresse email — I-AMU',
                '<h2>Bienvenue sur I-AMU !</h2>'
                . '<p>Cliquez sur le lien ci-dessous pour activer votre compte :</p>'
                . '<p><a href="' . htmlspecialchars($link) . '">Vérifier mon email</a></p>'
                . '<p>Si vous n\'avez pas créé de compte, ignorez cet email.</p>'
            );

            return ['success' => true, 'pending_verification' => true];
        } catch (\Throwable $e) {
            error_log('REGISTER ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return [
                'success' => false,
                'error'   => "Erreur lors de l'enregistrement. Merci de réessayer.",
            ];
        }
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function deactivateAccount(int $userId): array
    {
        try {
            $affected = $this->users->deactivate($userId);

            if ($affected === 0) {
                return [
                    'success' => false,
                    'error'   => 'Le compte est déjà désactivé ou introuvable.',
                ];
            }
            return ['success' => true];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => 'Erreur lors de la désactivation du compte.',
            ];
        }
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function reactivateAccount(string $email, string $password): array
    {
        if ($email === '' || $password === '') {
            return ['success' => false, 'error' => 'Email et mot de passe requis.'];
        }

        $row = $this->users->findByEmail($email);

        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            return ['success' => false, 'error' => 'Identifiants invalides.'];
        }

        if ($row['is_active']) {
            return ['success' => false, 'error' => 'Ce compte est déjà actif.'];
        }

        try {
            $this->users->reactivate((int) $row['id']);
            return ['success' => true];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => 'Erreur lors de la réactivation.',
            ];
        }
    }

    /**
     * Updates the current user's display name and keeps the session in sync
     * so the layout (avatar, breadcrumb) reflects it right away.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function updateProfile(int $userId, string $firstName, string $lastName): array
    {
        $firstName = trim($firstName);
        $lastName  = trim($lastName);

        if ($firstName === '' || $lastName === '') {
            return ['success' => false, 'error' => 'Le prénom et le nom sont obligatoires.'];
        }
        if (mb_strlen($firstName) > 50 || mb_strlen($lastName) > 100) {
            return ['success' => false, 'error' => 'Prénom (max 50) ou nom (max 100) trop long.'];
        }

        try {
            $this->users->updateName($userId, $firstName, $lastName);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Erreur lors de la mise à jour du profil.'];
        }

        $_SESSION['user_first_name'] = $firstName;
        $_SESSION['user_last_name']  = $lastName;

        return ['success' => true];
    }

    /**
     * Changes the current user's password after verifying the current one.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function changePassword(int $userId, string $current, string $new, string $confirm): array
    {
        if ($current === '' || $new === '' || $confirm === '') {
            return ['success' => false, 'error' => 'Tous les champs sont obligatoires.'];
        }
        if (strlen($new) < 8) {
            return ['success' => false, 'error' => 'Le nouveau mot de passe doit faire au moins 8 caractères.'];
        }
        if ($new !== $confirm) {
            return ['success' => false, 'error' => 'Les nouveaux mots de passe ne correspondent pas.'];
        }

        $row = $this->users->findById($userId);
        if ($row === null) {
            return ['success' => false, 'error' => 'Compte introuvable.'];
        }
        if (!password_verify($current, (string) $row['password_hash'])) {
            return ['success' => false, 'error' => 'Le mot de passe actuel est incorrect.'];
        }
        if (password_verify($new, (string) $row['password_hash'])) {
            return ['success' => false, 'error' => "Le nouveau mot de passe doit être différent de l'ancien."];
        }

        try {
            $this->users->updatePassword($userId, password_hash($new, PASSWORD_DEFAULT));
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Erreur lors du changement de mot de passe.'];
        }

        return ['success' => true];
    }


    /**
     * Validates the registration form input. Returns the (French) error
     * message to surface to the user, or null when every field is valid.
     */
    private function validateRegistration(
        string $email,
        string $password,
        string $passwordConfirm,
        string $firstName,
        string $lastName,
        bool $rgpdConsent
    ): ?string {
        if ($email === '' || $password === '' || $firstName === '' || $lastName === '') {
            return 'Tous les champs sont obligatoires.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Email invalide.';
        }
        if (strlen($password) < 8) {
            return 'Le mot de passe doit faire au moins 8 caractères.';
        }
        if ($password !== $passwordConfirm) {
            return 'Les mots de passe ne correspondent pas.';
        }
        if (!$rgpdConsent) {
            return 'Vous devez accepter les conditions RGPD pour créer un compte.';
        }

        return null;
    }

    /**
     * Registration for a researcher: lab derived from the email domain, no
     * department. Refused when no active domain links to a lab.
     *
     * @return array{role: string, department_id: null, laboratory_id: int}|array{error: string}
     */
    private function prepareResearcherRegistration(string $email): array
    {
        $domain = $this->domainOf($email);
        if ($domain === null) {
            return ['error' => 'Email invalide.'];
        }

        $laboratoryId = $this->emailDomains->findLaboratoryIdByDomain($domain);
        if ($laboratoryId === null) {
            return ['error' => "Ce domaine email n'est associé à aucun laboratoire."];
        }

        return [
            'role'          => 'researcher',
            'department_id' => null,
            'laboratory_id' => $laboratoryId,
        ];
    }

    /**
     * Registration for a student/teacher: department (validated against the
     * place) and role derived from the email domain.
     *
     * @return array{role: string, department_id: int, laboratory_id: null}|array{error: string}
     */
    private function prepareMemberRegistration(string $email, int $placeId, int $departmentId): array
    {
        // The dependent select is filled by client-side JS, so we re-check
        // server-side that the department exists and belongs to the place.
        if ($placeId === 0 || $departmentId === 0) {
            return ['error' => 'Veuillez choisir un lieu et un département.'];
        }
        if (!$this->places->departmentBelongsToPlace($departmentId, $placeId)) {
            return ['error' => 'Le département sélectionné est invalide.'];
        }

        // A researcher domain is not a valid member domain: reject it with the
        // same message rather than failing later on the NOT NULL laboratory_id.
        $role = $this->resolveRoleFromDomain($email);
        if ($role === null || $role === 'researcher') {
            return ['error' => "Ce domaine email n'est pas autorisé."];
        }

        return [
            'role'          => $role,
            'department_id' => $departmentId,
            'laboratory_id' => null,
        ];
    }

    /**
     * Returns the lowercased domain part of an email, or null when malformed.
     */
    private function domainOf(string $email): ?string
    {
        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return null;
        }

        return strtolower(substr($email, $atPos + 1));
    }

    /**
     * Maps an email to its role by looking up the part after the `@` in the
     * `email_domain_configs` table. Only active domains match. Returns the
     * role in the lowercase form createUserWithRole() expects
     * ('student' / 'teacher'), or null when the domain is unknown / disabled.
     */
    private function resolveRoleFromDomain(string $email): ?string
    {
        $domain = $this->domainOf($email);
        if ($domain === null) {
            return null;
        }

        // The table stores the SQL enum (UPPERCASE); the rest of the code
        // (createUserWithRole) works with lowercase role names.
        $role = $this->emailDomains->findRoleByDomain($domain);

        return $role === null ? null : strtolower($role);
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Resolves the user's roles by checking the specialisation tables.
     * A single user may carry multiple roles.
     *
     * @return list<string>
     */
    private function resolveRoles(int $userId): array
    {
        $roles = [];

        if ($this->users->hasRole($userId, 'teachers')) {
            $roles[] = 'teacher';
        }
        if ($this->users->hasRole($userId, 'students')) {
            $roles[] = 'student';
        }
        if ($this->users->hasRole($userId, 'department_administrators')) {
            $roles[] = 'department_admin';
        }
        if ($this->users->hasRole($userId, 'researchers')) {
            $roles[] = 'researcher';
        }

        return $roles;
    }
}
