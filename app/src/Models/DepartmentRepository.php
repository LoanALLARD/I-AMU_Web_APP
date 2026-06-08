<?php

declare(strict_types=1);

namespace Models;

use PDO;

/**
 * Read access for `departments` (a course a session hangs off). The owning
 * place is `departments.place_id`.
 */
class DepartmentRepository
{

    private PDO $pdo;

    public function __construct( PDO $pdo){
        $this->pdo = $pdo;
    }

    public function getDepartementFromUserId(int $userId){
        $query = $this->pdo->prepare(
            'SELECT name from departments d join users u on d.id = u.department_id where u.id = :id'
        );
        $query->execute(['id' => $userId]);

        $result = $query->fetchAll(\PDO::FETCH_ASSOC);

        if ($result === false) {
            return null;
        }

        return $result;
    }
}