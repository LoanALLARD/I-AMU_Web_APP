<?php
    require __DIR__ . '/vendor/autoload.php';

    use Src\Router;
    use Controller\controllerAccueil;
    use Controller\controllerChat;

    // routeur 
    $router = new Router();

    $router->add('GET','/',function(){
        $controller = new controllerAccueil();
        $controller->index();
    });

    $router->add('GET','/accueil',function(){
        $controller = new controllerAccueil();
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
        var_dump("la");
        $controller = new controllerChat();
        var_dump("lo");
        $controller->handleChat();
    });

    $uri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];

    $router->compare($uri, $method);
?>