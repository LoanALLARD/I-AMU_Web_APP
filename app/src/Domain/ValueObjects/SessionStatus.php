<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

/**
 * Lifecycle state of a Session.
 *
 * Stored values (DRAFT, SCHEDULED, CANCELLED, ENDED) map 1:1 to the
 * `session_status` PostgreSQL enum. ACTIVE is also a valid stored value
 * but is normally derived at read time from the schedule
 * (see {@see \App\Domain\Entities\Session::computedStatus()}).
 *
 * Transitions:
 *   DRAFT     -> SCHEDULED (when starts_at is set)
 *   SCHEDULED -> ACTIVE    (start()) | CANCELLED (cancel())
 *   ACTIVE    -> ENDED     (end())   | CANCELLED (cancel())
 *   ENDED     -> (terminal)
 *   CANCELLED -> (terminal)
 */
enum SessionStatus: string
{
    case Draft     = 'DRAFT';
    case Scheduled = 'SCHEDULED';
    case Active    = 'ACTIVE';
    case Ended     = 'ENDED';
    case Cancelled = 'CANCELLED';

    /**
     * Returns the user-facing French label.
     */
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

    /**
     * Returns the CSS badge class used by views.
     * Pairs with the badge styles in `sessions.css`.
     */
    public function badgeClass(): string
    {
        return 'badge-' . strtolower($this->value);
    }

    /**
     * True when the status is final and the session can no longer change.
     */
    public function isTerminal(): bool
    {
        return $this === self::Ended || $this === self::Cancelled;
    }
}
