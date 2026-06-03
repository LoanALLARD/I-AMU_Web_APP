<?php

namespace Data;

use PDO;
use PDOException;

// Using of the design pattern Singleton

class Database{

    // Store the single instance
    private static ?PDO $instance = null;

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



}