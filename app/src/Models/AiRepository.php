<?php

namespace Models;

use PDO;

/*
 * This class use PDO to recover 
 * all data about AI in the database
*/

class AiRepository{

    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function getModelByName(string $name_AI): ?array {
        $query = $this->pdo->prepare('
            SELECT * 
            FROM models 
            where name = :name');

        $query->execute(['name' => $name_AI]);
        $result = $query->fetch();

        if ($result === false) {
            return null;
        }
        return $result;
    }
    /**
     * Return all active model.
     * @return array<int, array>
     */

    public function getAllActiveModels(): array
    {
        $stmt = $this->pdo->query('
            SELECT id, name, version, provider, adapter, context_window, max_tokens
            FROM models
            WHERE is_active = TRUE
            ORDER BY provider, name
        ');

        return $stmt->fetchAll();
    }



}