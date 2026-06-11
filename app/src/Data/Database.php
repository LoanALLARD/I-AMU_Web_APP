<?php

namespace Data;

use PDO;
use PDOException;

/**
 * Singleton holder for the application's PDO connection.
 *
 * The only class allowed to instantiate PDO. Every Service and Repository
 * obtains the shared connection through {@see getConnection()} rather than
 * building its own. The connection targets PostgreSQL and reads its
 * credentials from `src/Config/config.php`.
 */
class Database{

    /** Single shared PDO connection, lazily created on first use. */
    private static ?PDO $instance = null;

    /**
     * Returns the shared PDO connection, opening it on first call.
     *
     * Configures PDO to throw exceptions on error, fetch rows as associative
     * arrays, and use real (non-emulated) prepared statements. The session
     * time zone is set from `config['timezone']` so `timestamptz` columns
     * (stored in UTC) are returned in local time for the UI; the value is
     * sanitised because `SET TIME ZONE` cannot take a bound parameter.
     *
     * On connection failure the real PDO error is logged and a generic
     * RuntimeException is thrown, so the global handler renders a clean 500
     * page without leaking the database host / credentials to the visitor.
     */
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
                // Log the real cause (host, driver message) server-side only;
                // never surface it to the client.
                error_log('[I-AMU] Database connection failed: ' . $e->getMessage());
                throw new \RuntimeException('Database connection failed.', 0, $e);
            }
        }

        return self::$instance;
    }



}