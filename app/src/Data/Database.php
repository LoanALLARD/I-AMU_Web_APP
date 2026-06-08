<?php

declare(strict_types=1);

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
            $dbConfig = $config['database'];

            $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}";

            try {
                self::$instance = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);

                // Return timestamptz values in the app timezone so the UI
                // shows local time (the column is stored in UTC). Sanitised
                // because SET TIME ZONE cannot take a bound parameter.
                $tz = preg_replace('/[^A-Za-z0-9\/_+-]/', '', (string) ($config['timezone'] ?? 'Europe/Paris'));
                self::$instance->exec("SET TIME ZONE '" . $tz . "'");
            } catch (PDOException $e) {
                die("Error while the connection with the database : " . $e->getMessage());
            }
        }

        return self::$instance;
    }



}