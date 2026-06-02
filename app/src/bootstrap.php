<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/autoload.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
} catch (Exception $e) {
    // On peut laisser couler ou afficher un message si le .env est obligatoire
    die("Erreur lors du chargement du fichier .env : " . $e->getMessage());
}

// Global view helpers (icon(), csrf_field()) — required by every page,
// loaded here so views never have to import the underlying namespaces.
require_once __DIR__ . '/Helpers/icons.php';
require_once __DIR__ . '/Helpers/csrf.php';