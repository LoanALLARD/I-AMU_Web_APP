<?php

require_once dirname(__DIR__ ). '/vendor/autoload.php';


try {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
} catch (Exception $e) {
    // On peut laisser couler ou afficher un message si le .env est obligatoire
    die("Erreur lors du chargement du fichier .env : " . $e->getMessage());
}