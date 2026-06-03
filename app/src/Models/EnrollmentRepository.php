<?php

declare(strict_types=1);

namespace Models;

use PDO;

/**
 * Data access for `enrollments` (a student joined to a session).
 */
class EnrollmentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function exists(int $studentId, int $sessionId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM enrollments WHERE student_id = :student AND session_id = :session');
        $stmt->execute(['student' => $studentId, 'session' => $sessionId]);

        return $stmt->fetchColumn() !== false;
    }

    public function enroll(int $studentId, int $sessionId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO enrollments (student_id, session_id) VALUES (:student, :session) ON CONFLICT DO NOTHING'
        );
        $stmt->execute(['student' => $studentId, 'session' => $sessionId]);
    }
}
