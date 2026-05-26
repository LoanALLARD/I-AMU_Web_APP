<?php

namespace App\Core;

/**
 * Routeur de l'application.
 * Gère l'enregistrement des routes et le dispatch vers les contrôleurs.
 */
class Router
{
    private array $routes = [];

    /**
     * Enregistre une route GET.
     */
    public function get(string $path, string $controller, string $action): void
    {
        $this->addRoute('GET', $path, $controller, $action);
    }

    /**
     * Enregistre une route POST.
     */
    public function post(string $path, string $controller, string $action): void
    {
        $this->addRoute('POST', $path, $controller, $action);
    }

    private function addRoute(string $method, string $path, string $controller, string $action): void
    {
        // Conversion des paramètres dynamiques {id} en regex
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'controller' => $controller,
            'action'     => $action,
        ];
    }

    /**
     * Dispatch la requête vers le bon contrôleur/action.
     */
    public function dispatch(string $uri, string $method): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                $controllerClass = $route['controller'];
                $action = $route['action'];

                if (!class_exists($controllerClass)) {
                    $this->sendError(500, "Contrôleur introuvable : $controllerClass");
                    return;
                }

                $controller = new $controllerClass();

                if (!method_exists($controller, $action)) {
                    $this->sendError(500, "Action introuvable : $action");
                    return;
                }

                call_user_func_array([$controller, $action], $params);
                return;
            }
        }

        $this->sendError(404, "Page introuvable");
    }

    /**
     * Affiche une page d'erreur.
     */
    private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        $controller = new \App\Controllers\ErrorController();
        $controller->show($code, $message);
    }
}
