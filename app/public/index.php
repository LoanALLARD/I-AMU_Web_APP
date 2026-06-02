<?php

/**
 * Front controller.
 *
 * Responsibilities:
 *   1. Load Dotenv + Composer autoloader (via src/bootstrap.php).
 *   2. Build the dependency graph (config → infrastructure → services →
 *      controllers).
 *   3. Register routes and dispatch the current request.
 *
 * Manual DI on purpose: the project rule (cf. CLAUDE.md §3) forbids an
 * automatic container.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

session_start();

use App\Application\Services\CancelSessionService;
use App\Application\Services\CreateSessionService;
use App\Application\Services\EndSessionService;
use App\Application\Services\GetSessionDashboardService;
use App\Application\Services\JoinSessionService;
use App\Application\Services\ListMySessionsService;
use App\Application\Services\StartSessionService;
use App\Application\Services\UpdateSessionService;
use App\Http\Controllers\SessionController;
use App\Infrastructure\Clock\SystemClock;
use App\Infrastructure\Persistence\PdoConnection;
use App\Infrastructure\Persistence\PdoModelRepository;
use App\Infrastructure\Persistence\PdoResourceRepository;
use App\Infrastructure\Persistence\PdoSessionRepository;
use Controllers\ChatController;
use Controllers\LLMController;
use Controllers\LoginController;
use Core\Controller;
use Core\Router;
use Services\AuthService;

// ─── Infrastructure (built once per request) ────────────────────────
$config      = require dirname(__DIR__) . '/src/Config/config.php';
$db          = new PdoConnection($config['database']);
$clock       = new SystemClock();
$authService = new AuthService($db);

// ─── Repositories ───────────────────────────────────────────────────
$sessionRepo  = new PdoSessionRepository($db);
$modelRepo    = new PdoModelRepository($db);
$resourceRepo = new PdoResourceRepository($db);

// ─── Application services (use-cases) ───────────────────────────────
$createSession   = new CreateSessionService($sessionRepo, $clock);
$updateSession   = new UpdateSessionService($sessionRepo, $clock);
$startSession    = new StartSessionService($sessionRepo, $clock);
$endSession      = new EndSessionService($sessionRepo, $clock);
$cancelSession   = new CancelSessionService($sessionRepo, $clock);
$joinSession     = new JoinSessionService($sessionRepo, $clock);
$listMySessions  = new ListMySessionsService($sessionRepo, $clock);
$getDashboard    = new GetSessionDashboardService($sessionRepo, $modelRepo, $clock);

// ─── Controllers ────────────────────────────────────────────────────
$sessionController = new SessionController(
    sessions:        $sessionRepo,
    models:          $modelRepo,
    resources:       $resourceRepo,
    createSession:   $createSession,
    updateSession:   $updateSession,
    startSession:    $startSession,
    endSession:      $endSession,
    cancelSession:   $cancelSession,
    joinSession:     $joinSession,
    listMySessions:  $listMySessions,
    getDashboard:    $getDashboard,
);

// Profile page — surfaces account info and acts as the exit point for
// /logout. Renders inside Layout/chat.php (the universal authenticated
// shell), so the sidebar + topbar wrap the profile cards uniformly.
$profileController = new class extends Controller {
    public function show(): void
    {
        $this->requireAuth();
        $this->render('pages/profile/index', [
            'page'      => 'profile',
            'pageTitle' => 'Mon profil',
            'user'      => $this->currentUser(),
        ], 'chat');
    }
};

// ─── Router ─────────────────────────────────────────────────────────
$router = new Router();

// Auth ----------------------------------------------------------------
$router->add('GET',  '/',             fn() => (new LoginController($authService))->showLogin());
$router->add('GET',  '/login',        fn() => (new LoginController($authService))->showLogin());
$router->add('POST', '/login',        fn() => (new LoginController($authService))->login());
$router->add('GET',  '/register',     fn() => (new LoginController($authService))->showRegister());
$router->add('POST', '/register',     fn() => (new LoginController($authService))->register());
$router->add('GET',  '/logout',       fn() => (new LoginController($authService))->logout());
$router->add('GET',  '/RGPDConsent',  fn() => (new LoginController($authService))->showRGPD());

// Chat — real shell from ServeurFolder (sidebar + composer + LLM) ----
$router->add('GET',  '/chat', fn() => (new ChatController())->index());
$router->add('POST', '/chat', fn() => (new LLMController())->handleChat());

// Profile -------------------------------------------------------------
$router->add('GET',  '/profile', fn() => $profileController->show());

// Sessions ------------------------------------------------------------
$router->add('GET',  '/sessions',             fn() => $sessionController->index());
$router->add('GET',  '/sessions/create',      fn() => $sessionController->create());
$router->add('POST', '/sessions/store',       fn() => $sessionController->store());
$router->add('POST', '/sessions/join',        fn() => $sessionController->join());
$router->add('GET',  '/sessions/{id}',        fn(string $id) => $sessionController->dashboard($id));
$router->add('GET',  '/sessions/{id}/edit',   fn(string $id) => $sessionController->edit($id));
$router->add('POST', '/sessions/{id}/update', fn(string $id) => $sessionController->update($id));
$router->add('POST', '/sessions/{id}/start',  fn(string $id) => $sessionController->start($id));
$router->add('POST', '/sessions/{id}/end',    fn(string $id) => $sessionController->end($id));
$router->add('POST', '/sessions/{id}/cancel', fn(string $id) => $sessionController->cancel($id));

// ─── Dispatch ───────────────────────────────────────────────────────
$router->dispatch(
    $_SERVER['REQUEST_URI']    ?? '/',
    $_SERVER['REQUEST_METHOD'] ?? 'GET'
);
