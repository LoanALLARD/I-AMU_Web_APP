<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

/**
 * Type of a session.
 *
 * Mirrors 1:1 the `session_type` PostgreSQL enum:
 *   - EXAM        — timed exam, no history, single allowed model, kraft UI
 *   - TUTORIAL    — TD-style guided session
 *   - LAB         — lab/practical work
 *   - FREE_STUDY  — open-ended exploration (sandbox)
 */
enum SessionType: string
{
    case Exam      = 'EXAM';
    case Tutorial  = 'TUTORIAL';
    case Lab       = 'LAB';
    case FreeStudy = 'FREE_STUDY';

    /**
     * Returns the user-facing French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Exam      => 'Examen',
            self::Tutorial  => 'TD',
            self::Lab       => 'TP',
            self::FreeStudy => 'Étude libre',
        };
    }

    /**
     * CSS class used by view badges. Matches the `.badge-*` declarations
     * in `sessions.css`.
     */
    public function badgeClass(): string
    {
        return 'badge-' . strtolower(str_replace('_', '-', $this->value));
    }

    /**
     * True for the only session type that triggers the kraft visual
     * lockdown on the student side (cf. spec 02 §1).
     */
    public function isExam(): bool
    {
        return $this === self::Exam;
    }
}
