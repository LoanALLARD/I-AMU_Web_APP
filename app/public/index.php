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

    $router->add('GET',  '/',            function() { (new AccueilController())->index(); });
    $router->add('GET',  '/accueil',     function() { (new AccueilController())->index(); });

    $router->add('POST', '/chat',        function() { (new LLMController())->handleChat(); });

    $uri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];

    $router->add('GET',  '/login',       function() { (new LoginController())->showLogin(); });
    $router->add('POST', '/login',       function() { (new LoginController())->login(); });
    $router->add('GET',  '/register',    function() { (new LoginController())->showRegister(); });
    $router->add('POST', '/register',    function() { (new LoginController())->register(); });
    $router->add('GET',  '/logout',      function() { (new LoginController())->logout(); });
    $router->add('GET',  '/RGPDConsent', function() { (new LoginController())->showRGPD(); });

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
