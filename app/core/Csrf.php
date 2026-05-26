<?php

namespace App\Core;

/**
 * Protection CSRF pour les formulaires.
 * Génère et vérifie des tokens anti-CSRF.
 */
class Csrf
{
    /**
     * Génère un token CSRF et le stocke en session.
     */
    public static function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    /**
     * Retourne le champ HTML caché avec le token CSRF.
     */
    public static function field(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Vérifie que le token soumis correspond à celui en session.
     */
    public static function verify(?string $submittedToken = null): bool
    {
        $submittedToken = $submittedToken ?? ($_POST['_csrf_token'] ?? '');
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($sessionToken) || empty($submittedToken)) {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }
}
