<?php

namespace Data;

use PDO;
use PDOException;

// Using of the design pattern Singleton

class Database{

    // Store the single instance
    private static ?PDO $instance = null;    
    
    // Database connection object
    private mysqli $connection;

    // Private constructor to prevent direct instantiation
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {

            $config = require __DIR__ . '/../Config/config.php';
            $dbConfig = $config['db'];

            $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";

            try {
                self::$instance = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                die("Error while the connection with the database : " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    // Function for recover all the information 
    // in the database about a model with it name
    // Use in LLMController
    public function getModelByName(string $name_AI){
        $query = "SELECT * from models WHERE name=$name_AI";
        $result=PDO::query($query);
    }
}