<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\EnrollmentRepositoryInterface;

/**
 * PostgreSQL implementation of {@see EnrollmentRepositoryInterface}.
 *
 * `enroll` relies on the `(student_id, session_id)` primary key plus
 * `ON CONFLICT DO NOTHING` so a duplicate join is silently absorbed at
 * the database level.
 */
final class PdoEnrollmentRepository extends PdoRepository implements EnrollmentRepositoryInterface
{
    public function exists(int $studentId, int $sessionId): bool
    {
        $row = $this->fetchOne(
            'SELECT 1 FROM enrollments
             WHERE student_id = :student AND session_id = :session',
            ['student' => $studentId, 'session' => $sessionId]
        );

        return $row !== null;
    }

    public function enroll(int $studentId, int $sessionId): void
    {
        $this->db->query(
            'INSERT INTO enrollments (student_id, session_id)
             VALUES (:student, :session)
             ON CONFLICT DO NOTHING',
            ['student' => $studentId, 'session' => $sessionId]
        );
    }
}
