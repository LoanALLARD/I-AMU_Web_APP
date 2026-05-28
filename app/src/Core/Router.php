<?php

namespace Core;

class Router{

    private array $routes = [];

    // Methode pour ajouter des routes
    public function add(string $method, string $path, callable $callback): void {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'callback' => $callback
        ];
    }

    //Analyse de la requete
    public function compare(string $requestUrl, string $requestedMethod): void {
        $path = parse_url ($requestUrl,PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if ($route['path'] == $path && $route['method'] == $requestedMethod){
                call_user_func($route['callback']);
                return;
            }
        }
        header("HTTP/1.0 404 Not Found");
        echo "Erreur 404 : Page introuvable";
    }
}