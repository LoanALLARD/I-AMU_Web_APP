<?php

/**
 * I-AMU - Point d'entrée de l'application.
 * Toutes les requêtes HTTP passent par ce fichier.
 */

// Autoloader natif (remplace vendor/autoload.php de Composer).
// Voir app/autoload.php pour la stratégie de résolution PSR-4.
require_once __DIR__ . '/../app/autoload.php';

// Démarrage de l'application
$app = \App\Core\Application::getInstance();
$app->run();
