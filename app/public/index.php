<?php
    // Hand-written autoloader (runtime). Composer's vendor/autoload.php
    // is reserved for dev tools (PHPStan, PHPUnit, PHPCS).
    
    require dirname(__DIR__) . '/src/bootstrap.php';

    session_start();
    use Core\Router;
    use Controllers\AccueilController;
    use Controllers\LLMController;
    use Controllers\AuthController;
    use Controllers\SessionController;
    use Controllers\DocumentController;
    use Controllers\ProfileController;
    use Controllers\PlaceController;
    use Controllers\DepartmentAdminController;
    use Controllers\ResearcherController;
    use Controllers\ErrorController;
    use Core\HttpException;

// routeur
    $router = new Router();

    $router->add('GET',  '/',            function() { (new AccueilController())->index(); });
    $router->add('GET',  '/accueil',     function() { (new AccueilController())->index(); });

    $router->add('POST', '/chat',         function() { (new LLMController())->handleChat(); });
    $router->add('POST', '/chat/new',     function() { (new AccueilController())->newChat(); });
    $router->add('POST', '/chat/rename',    function() { (new AccueilController())->renameChat(); });
    $router->add('POST', '/chat/archive',   function() { (new AccueilController())->archiveChat(); });
    $router->add('POST', '/chat/unarchive', function() { (new AccueilController())->unarchiveChat(); });

    $uri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];

    $router->add('GET',  '/login',       function() { (new AuthController())->showLogin(); });
    $router->add('POST', '/login',       function() { (new AuthController())->login(); });
    $router->add('GET',  '/register',    function() { (new AuthController())->showRegister(); });
    $router->add('POST', '/register',    function() { (new AuthController())->register(); });
    $router->add('GET',  '/logout',      function() { (new AuthController())->logout(); });
    $router->add('POST', '/reactivate',  function() { (new AuthController())->reactivate();});
    $router->add('GET',  '/rgpd_consent', function() { (new AuthController())->showRGPD(); });
    $router->add('GET',  '/verify-email',function() { (new AuthController())->verifyEmail(); });

    // AJAX: departments of a place, for the registration form's dependent select.
    $router->add('GET',  '/places/{id}/departments', function($id) { (new PlaceController())->departments($id); });

    // --- Chat home + profile (authenticated) --------------------------
    $router->add('GET',  '/chat',                function()     { (new AccueilController())->index(); });
    $router->add('GET',  '/chat/{id}',           function($id)  { (new AccueilController())->index($id); });
    $router->add('GET',  '/profile',             function()     { (new ProfileController())->index(); });
    $router->add('POST', '/profile/theme',       function()     { (new ProfileController())->updateTheme(); });
    $router->add('POST', '/profile/deactivate',  function()     { (new ProfileController())->deactivate(); });
    $router->add('POST', '/profile/update',      function()     { (new ProfileController())->updateProfile(); });
    $router->add('POST', '/profile/password',    function()     { (new ProfileController())->changePassword(); });

    // --- Department-admin console (department_admin role) --------------
    $router->add('GET',  '/department-admin',                         function() { (new DepartmentAdminController())->index(); });
    $router->add('GET',  '/department-admin/addModel',                function() { (new DepartmentAdminController())->fromModel(); });
    $router->add('POST', '/department-admin/addModel',                function() { (new DepartmentAdminController())->addModel(); });
    $router->add('POST', '/department-admin/researchers/approve',     function() { (new DepartmentAdminController())->approveResearcher(); });
    $router->add('POST', '/department-admin/researchers/reject',      function() { (new DepartmentAdminController())->rejectResearcher(); });
    $router->add('POST', '/department-admin/researchers/revoke',      function() { (new DepartmentAdminController())->revokeResearcher(); });
    $router->add('POST', '/department-admin/researchers/reauthorize', function() { (new DepartmentAdminController())->reauthorizeResearcher(); });
    $router->add('POST', '/department-admin/users/set-active',        function() { (new DepartmentAdminController())->setUserActive(); });

    // --- Researcher space (researcher role) ---------------------------
    $router->add('GET',  '/researcher',                 function() { (new ResearcherController())->index(); });
    $router->add('GET',  '/researcher/data',            function() { (new ResearcherController())->data(); });
    $router->add('POST', '/researcher/requests',        function() { (new ResearcherController())->requestAccess(); });
    $router->add('POST', '/researcher/requests/cancel', function() { (new ResearcherController())->cancelRequest(); });

// --- Sessions (teacher) + join (student) --------------------------
    // Literal routes are registered before the `{id}` wildcard so they win.
    $router->add('GET',  '/sessions',         function() { (new SessionController())->index(); });
    $router->add('GET',  '/sessions/create',  function() { (new SessionController())->create(); });
    $router->add('GET',  '/session/models-by-resource', function(){ (new SessionController())->getModelsByResource();});
    $router->add('POST', '/sessions/store',   function() { (new SessionController())->store(); });
    $router->add('GET',  '/sessions/join',    function() { (new SessionController())->showJoin(); });
    $router->add('POST', '/sessions/join',    function() { (new SessionController())->join(); });

    $router->add('GET',  '/sessions/{id}/edit',   function($id) { (new SessionController())->edit($id); });
    $router->add('POST', '/sessions/{id}/update', function($id) { (new SessionController())->update($id); });
    $router->add('POST', '/sessions/{id}/start',  function($id) { (new SessionController())->start($id); });
    $router->add('POST', '/sessions/{id}/end',    function($id) { (new SessionController())->end($id); });
    $router->add('POST', '/sessions/{id}/cancel', function($id) { (new SessionController())->cancel($id); });
    $router->add('GET',  '/sessions/{id}/monitor', function($id) { (new SessionController())->monitor($id); });
    $router->add('GET',  '/sessions/{id}/export',  function($id) { (new SessionController())->export($id); });
    $router->add('POST', '/sessions/{id}/documents', function($id) { (new DocumentController())->uploadToSession($id); });
    $router->add('GET',  '/sessions/{id}',        function($id) { (new SessionController())->dashboard($id); });

    $router->add('POST', '/documents/{id}/delete', function($id) { (new DocumentController())->delete($id); });
    $router->add('GET',  '/documents/session_{sessionId}/{docId}', function($sessionId, $docId) { (new DocumentController())->download($sessionId, $docId); });

    try {
        $router->compare($uri, $method);
    } catch (HttpException $e) {
        (new ErrorController())->show($e->statusCode(), $e);
    } catch (\Throwable $e) {
        error_log('[I-AMU] Uncaught ' . $e::class . ': ' . $e->getMessage()
            . ' @ ' . $e->getFile() . ':' . $e->getLine());
        (new ErrorController())->show(500, $e);
    }
?>

