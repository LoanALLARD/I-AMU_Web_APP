<?php

declare(strict_types=1);

namespace App\Application\Ports;

/**
 * Persistence port for session enrollments.
 *
 * The underlying table's primary key `(student_id, session_id)` is what
 * physically guarantees a student cannot join the same session twice.
 */
interface EnrollmentRepositoryInterface
{
    public function exists(int $studentId, int $sessionId): bool;

    /**
     * Enrolls a student. Idempotent: enrolling an already-enrolled student
     * is a no-op (no exception).
     */
    public function enroll(int $studentId, int $sessionId): void;
}
