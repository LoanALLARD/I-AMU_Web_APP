<?php

namespace App\Core;

/**
 * Classe principale de l'application I-AMU.
 * Initialise la configuration, la session et dispatch les requêtes via le routeur.
 */
class Application
{
    private static ?Application $instance = null;
    private Router $router;
    private array $config;

    private function __construct()
    {
        $this->config = require __DIR__ . '/../config/config.php';
        date_default_timezone_set($this->config['app']['timezone']);

        // Charge les helpers globaux (fonctions utilitaires de vue)
        require_once __DIR__ . '/../helpers/icons.php';
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Démarre l'application : session, routing, dispatch.
     */
    public function run(): void
    {
        // Démarrage de la session PHP
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Initialisation du routeur et chargement des routes
        $this->router = new Router();
        $this->loadRoutes();

        // Dispatch de la requête courante
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        $this->router->dispatch($uri, $method);
    }

    /**
     * Charge les définitions de routes.
     */
    private function loadRoutes(): void
    {
        $router = $this->router;
        require __DIR__ . '/../config/routes.php';
    }

    /**
     * Accès à la configuration.
     */
    public function getConfig(string $key = ''): mixed
    {
        if ($key === '') {
            return $this->config;
        }

        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }
}
