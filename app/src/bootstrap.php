<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';


// Load app/.env if present, but do NOT fail when it is missing: in Docker
// the DB_* / OLLAMA_URL vars are injected by docker-compose (`environment:`
// on the php-app service) straight into the process env, and config.php
// reads them from $_ENV with sane defaults. safeLoad() (vs load()) means a
// missing/ignored .env file no longer kills every request.
try {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
} catch (Exception $e) {
    // Only reached on a malformed .env, not on a missing one.
    die("Erreur lors du chargement du fichier .env : " . $e->getMessage());
}

// Pin the PHP runtime to the app timezone so datetime-local form inputs are
// read as local time and `new DateTimeImmutable('now')` is local. The DB
// connection is pinned to the same zone in Data\Database.
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Paris');

// Global view helpers (icon(), csrf_field()) — required by every page,
// loaded here so views never have to import the underlying namespaces.
require_once __DIR__ . '/Helpers/icons.php';
require_once __DIR__ . '/Helpers/csrf.php';
require_once __DIR__ . '/Helpers/branding.php';