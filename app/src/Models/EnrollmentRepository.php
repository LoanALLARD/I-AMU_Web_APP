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

    /**
     * True only when the student has an *active* enrollment in the session
     * (false when not enrolled, or enrolled but deactivated by the teacher).
     */
    public function isActive(int $studentId, int $sessionId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM enrollments
              WHERE student_id = :student AND session_id = :session AND is_active = TRUE'
        );
        $stmt->execute(['student' => $studentId, 'session' => $sessionId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Activates or deactivates a student's enrollment in a session. Used by the
     * teacher from the monitor view to remove a student (and bar re-joining).
     */
    public function setActive(int $studentId, int $sessionId, bool $active): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE enrollments SET is_active = :active
              WHERE student_id = :student AND session_id = :session'
        );
        $stmt->bindValue(':active', $active, PDO::PARAM_BOOL);
        $stmt->bindValue(':student', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':session', $sessionId, PDO::PARAM_INT);
        $stmt->execute();
    }
}
