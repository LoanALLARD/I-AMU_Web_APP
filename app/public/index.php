<?php
    // Hand-written autoloader (runtime). Composer's vendor/autoload.php
    // is reserved for dev tools (PHPStan, PHPUnit, PHPCS).
    
    //require dirname(__DIR__) . '/autoload.php';
    require dirname(__DIR__) . '/src/bootstrap.php';

    session_start();
    use Core\Router;
    use Controllers\AccueilController;
    use Controllers\LLMController;
    use Controllers\LoginController;
    use Controllers\SessionController;
    use Controllers\ProfileController;

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
        $controller = new  LoginController();
        $controller->showRGPD();
    });

    // --- Chat home + profile (authenticated) --------------------------
    $router->add('GET', '/chat',      function()    { (new AccueilController())->index(); });
    $router->add('GET', '/chat/{id}', function($id)  { (new AccueilController())->index(); });
    $router->add('GET', '/profile',   function()    { (new ProfileController())->index(); });

    // --- Sessions (teacher) + join (student) --------------------------
    // Literal routes are registered before the `{id}` wildcard so they win.
    $router->add('GET',  '/sessions',         function() { (new SessionController())->index(); });
    $router->add('GET',  '/sessions/create',  function() { (new SessionController())->create(); });
    $router->add('POST', '/sessions/store',   function() { (new SessionController())->store(); });
    $router->add('GET',  '/sessions/join',    function() { (new SessionController())->showJoin(); });
    $router->add('POST', '/sessions/join',    function() { (new SessionController())->join(); });

    $router->add('GET',  '/sessions/{id}/edit',   function($id) { (new SessionController())->edit($id); });
    $router->add('POST', '/sessions/{id}/update', function($id) { (new SessionController())->update($id); });
    $router->add('POST', '/sessions/{id}/start',  function($id) { (new SessionController())->start($id); });
    $router->add('POST', '/sessions/{id}/end',    function($id) { (new SessionController())->end($id); });
    $router->add('POST', '/sessions/{id}/cancel', function($id) { (new SessionController())->cancel($id); });
    $router->add('GET',  '/sessions/{id}',        function($id) { (new SessionController())->dashboard($id); });

    $router->compare($uri, $method);
?>
