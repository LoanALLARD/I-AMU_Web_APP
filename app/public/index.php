<?php
    require dirname(__DIR__) . '/vendor/autoload.php';
    use Core\Router;
    use Controller\AccueilController;
    use Controller\ChatController;

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
        $controller = new ChatController();
        $controller->handleChat();
    });

    $uri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];

    $router->compare($uri, $method);
?>