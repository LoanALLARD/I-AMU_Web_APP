<?php
    // Hand-written autoloader (runtime). Composer's vendor/autoload.php
    // is reserved for dev tools (PHPStan, PHPUnit, PHPCS).
    require dirname(__DIR__) . '/autoload.php';
    session_start();
    use Core\Router;
    use Controllers\AccueilController;
    use Controllers\LLMController;
    use Controllers\LoginController;

    // routeur 
    $router = new Router();

    $router->add('GET','/',function(){
        $controller = new AccueilController();
        $controller->index();
    });

    $router->add('GET','/accueil',function(){
        $controller = new AccueilController();
        $controller->index();
    });

    //L'api doit prendre la forme suivante pour envoyer un prompt 
    // de l'application vers le serveur ollama
    /*
    *   curl http://localhost:8082/api/generate -d '{
            "model": "llama3.2:1b",
            "prompt": "raconte moi une histoire",
            "stream": false,
            "format":"json"
            }'
    *
    format :                curl -X POST -d "data" URL
    commande cible :        curl -X POST -d '{
                                "model"   : "....",
                                "message" : "....",
                                "context" : "[..]"
                            }'
                            http://localhost:8085/chat
    *
    *
    *
    */

    $router->add('POST','/chat',function(){
        $controller = new LLMController();
        $controller->handleChat();
    });

    $uri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];

    $router->add('GET', '/login', function() {
        $controller = new LoginController();
        $controller->showLogin();
    });

    $router->add('POST', '/login', function() {
        $controller = new LoginController();
        $controller->login();
    });

    $router->add('GET', '/register', function() {
        $controller = new LoginController();
        $controller->showRegister();
    });

    $router->add('POST', '/register', function() {
        $controller = new LoginController();
        $controller->register();
    });

    $router->add('GET', '/logout', function() {
        $controller = new LoginController();
        $controller->logout();
    });

    $router->add('GET','/RGPDConsent',function(){
        $controller = new AccueilController();
        $controller->index();
    });

    $router->compare($uri, $method);
?>