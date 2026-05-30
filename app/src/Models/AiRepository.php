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

    public function getModelByName(string $name_AI){
        $query = $this->pdo->prepare('
        SELECT * FROM models where name = :name
        ');

        $query->execute(['name' => $name_AI]);

        $result = $query->fetch();

        if ($result === false) {
            return null;
        }

        return $result;

    }


}