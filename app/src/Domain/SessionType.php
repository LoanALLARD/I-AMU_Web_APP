<?php

declare(strict_types=1);

namespace Domain;

/**
 * Pedagogical type of a session. String-backed to map to `sessions.type`.
 */
enum SessionType: string
{
    case Exam      = 'EXAM';
    case Tutorial  = 'TUTORIAL';
    case Lab       = 'LAB';
    case FreeStudy = 'FREE_STUDY';

    public function label(): string
    {
        return match ($this) {
            self::Exam      => 'Examen',
            self::Tutorial  => 'TD',
            self::Lab       => 'TP',
            self::FreeStudy => 'Étude libre',
        };
    }

    public function badgeClass(): string
    {
        return 'badge-' . strtolower(str_replace('_', '-', $this->value));
    }

    public function isExam(): bool
    {
        return $this === self::Exam;
    }
}
