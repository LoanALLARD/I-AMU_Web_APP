<?php

namespace Models;

use PDO;

class UserRepository{
    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUserByEmail(string $email): ?array
    {
        $query = $this->pdo->prepare('
        SELECT * FROM users where email = :email
        ');

        $query->execute(['email'=> $email]);

        $result = $query->fetch();

        if ($result === false) {
            return null;
        }

        return $result;
    }
}