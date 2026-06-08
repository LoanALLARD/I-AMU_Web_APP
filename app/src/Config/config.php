<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * Values come from environment variables (injected by Docker or loaded
 * from app/.env via Dotenv in bootstrap.php).
 *
 * The DB block is consumed by
 * App\Infrastructure\Persistence\PdoConnection (the single connection
 * point). The legacy `db` key (used by the removed Data\Database
 * singleton) was dropped when the ServeurFolder chat stack was migrated
 * to Clean Architecture.
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

    // Application timezone. PostgreSQL stores timestamptz in UTC; the PHP
    // runtime and the DB connection are both pinned to this zone so stored
    // instants are entered and displayed in local (AMU = France) time.
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Europe/Paris',
    'mail' => [
        'host' => $_ENV['SMTP_HOST'] ?? 'mailpit',
        'port' => (int) ($_ENV['SMTP_PORT'] ?? 1025),
        'from' => $_ENV['SMTP_FROM'] ?? 'noreply@iamu.univ-amu.fr',
    ],

    'app' => [
        'url' => $_ENV['APP_URL'] ?? 'http://localhost:8085',
    ],
    // Debug mode (APP_DEBUG=true in dev). When on, the error page shows the
    // exception type, location and trace. Keep it OFF in production.
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
];
