<?php

/**
 * I-AMU - Point d'entrée de l'application.
 * Toutes les requêtes HTTP passent par ce fichier.
 */

// Autoloader Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Démarrage de l'application
$app = \App\Core\Application::getInstance();
$app->run();
