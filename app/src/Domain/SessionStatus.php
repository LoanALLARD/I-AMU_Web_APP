<?php

declare(strict_types=1);

namespace Domain;

/**
 * Lifecycle status of a session. String-backed so it maps straight to the
 * `sessions.status` column. Carries its own French label and badge class so
 * controllers/views never hardcode presentation strings.
 */
enum SessionStatus: string
{
    case Draft     = 'DRAFT';
    case Scheduled = 'SCHEDULED';
    case Active    = 'ACTIVE';
    case Ended     = 'ENDED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Brouillon',
            self::Scheduled => 'Planifiée',
            self::Active    => 'En cours',
            self::Ended     => 'Terminée',
            self::Cancelled => 'Annulée',
        };
    }

    public function badgeClass(): string
    {
        return 'badge-' . strtolower($this->value);
    }

    public function isTerminal(): bool
    {
        return $this === self::Ended || $this === self::Cancelled;
    }
}
