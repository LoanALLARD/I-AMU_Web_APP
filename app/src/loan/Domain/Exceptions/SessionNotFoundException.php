<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

/**
 * Thrown when a session is looked up by id or access code and does not exist.
 */
final class SessionNotFoundException extends SessionException
{
    public static function withId(int $id): self
    {
        return new self("Session #{$id} introuvable.");
    }

    public static function withAccessCode(string $code): self
    {
        return new self("Aucune session avec le code « {$code} ».");
    }
}
