<?php

declare(strict_types=1);

namespace Core;

/**
 * Single source of truth for password strength. Every account creation and
 * password change calls this so the server enforces the same rule as the
 * forms (min 12 chars, one uppercase, one digit, one special character).
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    /**
     * Returns a French error message when the password is too weak, or null
     * when it satisfies the policy.
     */
    public static function validate(string $password): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'Le mot de passe doit faire au moins ' . self::MIN_LENGTH . ' caractères.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Le mot de passe doit contenir au moins une majuscule.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Le mot de passe doit contenir au moins un chiffre.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Le mot de passe doit contenir au moins un caractère spécial.';
        }
        return null;
    }
}