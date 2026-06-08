<?php

declare(strict_types=1);

namespace Domain;

use DomainException;

/**
 * Single domain exception for the Session aggregate. Named factories replace
 * the per-rule exception classes of the previous hexagonal design - the HTTP
 * layer only needs the message to flash, not the concrete type.
 */
final class SessionException extends DomainException
{
    public static function notFound(int $id): self
    {
        return new self("Session #{$id} introuvable.");
    }

    public static function notFoundByCode(string $code): self
    {
        return new self("Aucune session avec le code « {$code} ».");
    }

    public static function alreadyStarted(): self
    {
        return new self('Cette session est déjà démarrée.');
    }

    public static function alreadyEnded(): self
    {
        return new self('Cette session est déjà terminée.');
    }

    public static function cancelled(): self
    {
        return new self('Cette session a été annulée.');
    }

    public static function notEditable(): self
    {
        return new self('Cette session ne peut plus être modifiée.');
    }

    public static function notAvailable(string $message): self
    {
        return new self($message);
    }
}
