<?php

/**
 * Application configuration.
 *
 * Values come from environment variables (injected by Docker or loaded
 * from app/.env via Dotenv in bootstrap.php).
 *
 * The DB block is exposed under TWO keys so both consumer styles work
 * during the Sessions/ServeurFolder convergence:
 *   - `database.{host,port,name,user,password}` — consumed by
 *     App\Infrastructure\Persistence\PdoConnection (Sessions stack).
 *   - `db.{host,port,dbname,user,password}` — consumed by
 *     Data\Database::getConnection() (ServeurFolder legacy singleton).
 * Both point at the same env vars so they cannot drift.
 */

$dbHost     = $_ENV['DB_HOST']     ?? 'db';
$dbPort     = (int) ($_ENV['DB_PORT'] ?? 5432);
$dbName     = $_ENV['DB_NAME']     ?? 'iamu';
$dbUser     = $_ENV['DB_USER']     ?? 'iamu_user';
$dbPassword = $_ENV['DB_PASSWORD'] ?? '';

return [
    'database' => [
        'host'     => $dbHost,
        'port'     => $dbPort,
        'name'     => $dbName,
        'user'     => $dbUser,
        'password' => $dbPassword,
    ],
    'db' => [
        'host'     => $dbHost,
        'port'     => (string) $dbPort,
        'dbname'   => $dbName,
        'user'     => $dbUser,
        'password' => $dbPassword,
    ],
];
