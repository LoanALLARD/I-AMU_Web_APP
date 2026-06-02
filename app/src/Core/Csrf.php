<?php

declare(strict_types=1);

namespace Core;

/**
 * Anti-CSRF token generator and verifier.
 *
 * Generates a 32-byte hex token stored in the session and exposes a hidden
 * form field plus a verification helper. Ported from POC `app/core/Csrf.php`
 * with the runtime namespace adapted (`App\Core` -> `Core`).
 */
final class Csrf
{
    /**
     * Returns the current session's CSRF token, generating it on first
     * use. Subsequent calls in the same session reuse it — crucial so
     * a page rendering several forms (e.g. the session dashboard with
     * Start / End / Cancel buttons) emits the SAME token everywhere
     * and matches whatever the browser later submits.
     */
    public static function generateToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Returns the hidden form field markup with the session's token.
     * Calling this multiple times on the same render produces the same
     * value (see {@see generateToken()}).
     */
    public static function field(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    }

    /**
     * Rotates the token. Call this on sensitive boundary events such as
     * login / logout to invalidate any in-flight pre-login form.
     */
    public static function rotate(): string
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    /**
     * Verifies that the submitted token matches the one stored in the session.
     * If no token is passed, falls back to `$_POST['_csrf_token']`.
     * Uses `hash_equals` to prevent timing attacks.
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
