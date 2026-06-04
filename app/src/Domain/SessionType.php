<?php

declare(strict_types=1);

namespace Domain;

/**
 * Pedagogical type of a session. String-backed to map to `sessions.type`.
 */
enum SessionType: string
{
    case Course = 'COURSE';
    case Exam   = 'EXAM';

    public function label(): string
    {
        return match ($this) {
            self::Course => 'Cours',
            self::Exam   => 'Examen',
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
