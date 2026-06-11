<?php
// bootstrap.php loads Composer's PSR-4 autoloader (vendor/autoload.php),
// shared by the runtime and the dev tools (PHPStan, PHPUnit, PHPCS).

require dirname(__DIR__) . '/src/bootstrap.php';

// Harden the session cookie before the session starts.
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
    'path' => '/',
]);
ini_set('session.use_strict_mode', '1');

session_start();
use Core\Router;
use Controllers\HomeController;
use Controllers\LLMController;
use Controllers\AuthController;
use Controllers\SessionController;
use Controllers\DocumentController;
use Controllers\ProfileController;
use Controllers\PlaceController;
use Controllers\DepartmentAdminController;
use Controllers\ResearcherController;
use Controllers\SuperAdminAuthController;
use Controllers\SuperAdminController;
use Controllers\ErrorController;
use Controllers\ResourceController;
use Core\HttpException;

// routeur
$router = new Router();

$router->add('GET', '/', function () {
    (new HomeController())->index();
});
$router->add('GET', '/accueil', function () {
    (new HomeController())->index();
});

$router->add('POST', '/chat', function () {
    (new LLMController())->handleChat();
});
$router->add('POST', '/chat/documents', function () {
    (new DocumentController())->uploadToConversation();
});
$router->add('POST', '/chat/documents/{id}/delete', function ($id) {
    (new DocumentController())->deleteFromConversation($id);
});
$router->add('POST', '/chat/rename', function () {
    (new HomeController())->renameChat();
});
$router->add('POST', '/chat/archive', function () {
    (new HomeController())->archiveChat();
});
$router->add('POST', '/chat/unarchive', function () {
    (new HomeController())->unarchiveChat();
});
$router->add('POST', '/chat/feedback', function () {
    (new LLMController())->recordFeedback();
});

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$router->add('GET', '/login', function () {
    (new AuthController())->showLogin();
});
$router->add('POST', '/login', function () {
    (new AuthController())->login();
});
$router->add('POST', '/domain_name', function () {
    (new AuthController())->getDomainName();
});
$router->add('GET', '/register', function () {
    (new AuthController())->showRegister();
});
$router->add('POST', '/register', function () {
    (new AuthController())->register();
});
$router->add('GET', '/logout', function () {
    (new AuthController())->logout();
});
$router->add('POST', '/reactivate', function () {
    (new AuthController())->reactivate();
});
$router->add('GET', '/gdpr_consent', function () {
    (new AuthController())->showGDPR();
});
$router->add('GET', '/gdpr_consent_researcher', function () {
    (new AuthController())->showRGPDResearcher();
});
$router->add('GET', '/verify-email', function () {
    (new AuthController())->verifyEmail();
});
$router->add('GET', '/forgot-password', function () {
    (new AuthController())->showForgotPassword();
});
$router->add('POST', '/forgot-password', function () {
    (new AuthController())->forgotPassword();
});
$router->add('GET', '/reset-password', function () {
    (new AuthController())->showResetPassword();
});
$router->add('POST', '/reset-password', function () {
    (new AuthController())->resetPassword();
});

// AJAX: departments of a place, for the registration form's dependent select.
$router->add('GET', '/places/{id}/departments', function ($id) {
    (new PlaceController())->departments($id);
});

// --- Chat home + profile (authenticated) --------------------------
$router->add('GET', '/chat', function () {
    (new HomeController())->index();
});
$router->add('GET', '/chat/session-status', function () {
    (new HomeController())->sessionStatus();
});
$router->add('GET', '/chat/documents/{convId}', function ($convId) {
    (new DocumentController())->conversationDocuments($convId);
});
$router->add('GET', '/chat/{id}', function ($id) {
    (new HomeController())->index($id);
});
$router->add('GET', '/profile', function () {
    (new ProfileController())->index();
});
$router->add('POST', '/profile/theme', function () {
    (new ProfileController())->updateTheme();
});
$router->add('POST', '/profile/deactivate', function () {
    (new ProfileController())->deactivate();
});
$router->add('POST', '/profile/update', function () {
    (new ProfileController())->updateProfile();
});
$router->add('POST', '/profile/password', function () {
    (new ProfileController())->changePassword();
});
$router->add('POST', '/profile/request-specialisation', function () {
    (new ProfileController())->requestSpecialisation();
});
$router->add('POST', '/profile/withdraw-consent', function () {
    (new ProfileController())->updateResearchOpposition();
});

// --- Department-admin console (department_admin role) --------------
$router->add('GET', '/department-admin', function () {
    (new DepartmentAdminController())->index();
});
$router->add('GET', '/department-admin/users', function () {
    (new DepartmentAdminController())->users();
});
$router->add('GET',  '/department-admin/models', function() { 
    (new DepartmentAdminController())->models();});

$router->add('POST', '/department-admin/models/archive', function() { 
    (new DepartmentAdminController())->archiveModel();
});
$router->add('POST', '/department-admin/models/reactivate', function() { 
    (new DepartmentAdminController())->reactivateModel();
});
$router->add('GET', '/department-admin/search', function () {
    (new DepartmentAdminController())->GetUsersByName();
});
$router->add('GET', '/department-admin/addModel', function () {
    (new DepartmentAdminController())->formModel();
});
$router->add('POST', '/department-admin/addModel', function () {
    (new DepartmentAdminController())->addModel();
});
$router->add('POST', '/department-admin/researchers/approve', function () {
    (new DepartmentAdminController())->approveResearcher();
});
$router->add('POST', '/department-admin/researchers/reject', function () {
    (new DepartmentAdminController())->rejectResearcher();
});
$router->add('POST', '/department-admin/researchers/revoke', function () {
    (new DepartmentAdminController())->revokeResearcher();
});
$router->add('POST', '/department-admin/researchers/reauthorize', function () {
    (new DepartmentAdminController())->reauthorizeResearcher();
});
$router->add('POST', '/department-admin/specialisations/approve', function () {
    (new DepartmentAdminController())->approveSpecialisation();
});
$router->add('POST', '/department-admin/specialisations/reject', function () {
    (new DepartmentAdminController())->rejectSpecialisation();
});
$router->add('POST', '/department-admin/specialisations/revoke', function () {
    (new DepartmentAdminController())->revokeSpecialisation();
});
$router->add('POST', '/department-admin/specialisations/reauthorize', function () {
    (new DepartmentAdminController())->reauthorizeSpecialisation();
});
$router->add('POST', '/department-admin/users/set-active', function () {
    (new DepartmentAdminController())->setUserActive();
});

// --- Super admin (isolated session, URL-only — no internal link) --
// Dedicated login + panel, reachable only by direct URL (decision D1,
// SPEC-superadmin-auth.md). Never linked from the user-facing app.
$router->add('GET', '/super-admin/login', function () {
    (new SuperAdminAuthController())->showLogin();
});
$router->add('POST', '/super-admin/login', function () {
    (new SuperAdminAuthController())->login();
});
$router->add('POST', '/super-admin/logout', function () {
    (new SuperAdminAuthController())->logout();
});
$router->add('GET', '/super-admin/settings', function () {
    (new SuperAdminController())->showSetting();
});
$router->add('POST', '/super-admin/settings/update', function () {
    (new SuperAdminController())->updateInfo();
});
$router->add('GET', '/super-admin', function () {
    (new SuperAdminController())->index();
});
$router->add('GET', '/super-admin/department-admins', function () {
    (new SuperAdminController())->departmentAdmins();
});
$router->add('GET', '/super-admin/places', function () {
    (new SuperAdminController())->places();
});
$router->add('POST', '/super-admin/places', function () {
    (new SuperAdminController())->addPlace();
});
$router->add('POST', '/super-admin/places/delete', function () {
    (new SuperAdminController())->deletePlace();
});
$router->add('POST', '/super-admin/places/branding', function () {
    (new SuperAdminController())->updateBranding();
});
$router->add('POST', '/super-admin/departments', function () {
    (new SuperAdminController())->addDepartment();
});
$router->add('POST', '/super-admin/departments/toggle', function () {
    (new SuperAdminController())->toggleDepartment();
});
$router->add('GET', '/super-admin/email-domains', function () {
    (new SuperAdminController())->emailDomains();
});
$router->add('POST', '/super-admin/email-domains', function () {
    (new SuperAdminController())->addEmailDomain();
});
$router->add('POST', '/super-admin/email-domains/role', function () {
    (new SuperAdminController())->changeEmailDomainRole();
});
$router->add('POST', '/super-admin/email-domains/toggle', function () {
    (new SuperAdminController())->toggleEmailDomain();
});
$router->add('POST', '/super-admin/department-admins/invite', function () {
    (new SuperAdminController())->inviteDepartmentAdmin();
});
$router->add('POST', '/super-admin/super-admins/invite', function () {
    (new SuperAdminController())->inviteSuperAdmin();
});
$router->add('POST', '/super-admin/department-admins/revoke', function () {
    (new SuperAdminController())->revokeDepartmentAdmin();
});

//Admin invitation
$router->add('GET', '/admin-invite/accept', function () {
    (new AuthController())->showAcceptInvite();
});
$router->add('POST', '/admin-invite/accept', function () {
    (new AuthController())->acceptInvite();
});


// --- Researcher space (researcher role) ---------------------------
$router->add('GET', '/researcher', function () {
    (new ResearcherController())->index();
});
$router->add('GET', '/researcher/data', function () {
    (new ResearcherController())->data();
});
$router->add('GET', '/researcher/data/stats', function () {
    (new ResearcherController())->stats();
});
$router->add('GET', '/researcher/export', function () {
    (new ResearcherController())->export();
});
$router->add('GET', '/researcher/export/download', function () {
    (new ResearcherController())->exportDownload();
});
$router->add('POST', '/researcher/requests', function () {
    (new ResearcherController())->requestAccess();
});
$router->add('POST', '/researcher/requests/cancel', function () {
    (new ResearcherController())->cancelRequest();
});

// --- Sessions (teacher) + join (student) --------------------------
// Literal routes are registered before the `{id}` wildcard so they win.
$router->add('GET', '/sessions', function () {
    (new SessionController())->index();
});
$router->add('GET', '/sessions/create', function () {
    (new SessionController())->create();
});
$router->add('GET', '/session/models-by-resource', function () {
    (new SessionController())->getModelsByResource();
});
$router->add('POST', '/sessions/store', function () {
    (new SessionController())->store();
});
$router->add('GET', '/sessions/join', function () {
    (new SessionController())->showJoin();
});
$router->add('POST', '/sessions/join', function () {
    (new SessionController())->join();
});

$router->add('GET', '/sessions/{id}/edit', function ($id) {
    (new SessionController())->edit($id);
});
$router->add('POST', '/sessions/{id}/update', function ($id) {
    (new SessionController())->update($id);
});
$router->add('POST', '/sessions/{id}/start', function ($id) {
    (new SessionController())->start($id);
});
$router->add('POST', '/sessions/{id}/end', function ($id) {
    (new SessionController())->end($id);
});
$router->add('POST', '/sessions/{id}/cancel', function ($id) {
    (new SessionController())->cancel($id);
});
$router->add('GET', '/sessions/{id}/monitor', function ($id) {
    (new SessionController())->monitor($id);
});
$router->add('GET', '/sessions/{id}/stats', function ($id) {
    (new SessionController())->stats($id);
});
$router->add('GET', '/sessions/{id}/export', function ($id) {
    (new SessionController())->export($id);
});
$router->add('POST', '/sessions/{id}/documents', function ($id) {
    (new DocumentController())->uploadToSession($id);
});
$router->add('POST', '/sessions/{id}/student-status', function ($id) {
    (new SessionController())->setStudentActive($id);
});
$router->add('GET', '/sessions/{id}', function ($id) {
    (new SessionController())->dashboard($id);
});

$router->add('GET', '/ressources', function () {
    (new ResourceController())->index();
});
$router->add('GET', '/ressources/create', function () {
    (new ResourceController())->create();
});
$router->add('POST', '/ressources/store', function () {
    (new ResourceController())->store();
});
$router->add('GET', '/ressources/{id}/edit', function ($id) {
    (new ResourceController())->edit($id);
});
$router->add('POST', '/ressources/{id}/update', function ($id) {
    (new ResourceController())->update($id);
});
$router->add('POST', '/ressources/{id}/archive', function ($id) {
    (new ResourceController())->archive($id);
});
$router->add('POST', '/ressources/{id}/restore', function ($id) {
    (new ResourceController())->restore($id);
});
$router->add('POST', '/ressources/{id}/publish', function ($id) {
    (new ResourceController())->publish($id);
});

$router->add('POST', '/documents/{id}/delete', function ($id) {
    (new DocumentController())->delete($id);
});
$router->add('GET', '/documents/session_{sessionId}/{docId}', function ($sessionId, $docId) {
    (new DocumentController())->download($sessionId, $docId);
});
$router->add('GET', '/documents/conversation_{conversationId}/{docId}', function ($conversationId, $docId) {
    (new DocumentController())->downloadFromConversation($conversationId, $docId);
});

try {
    $router->dispatch($uri, $method);
} catch (HttpException $e) {
    (new ErrorController())->show($e->statusCode(), $e);
} catch (\Throwable $e) {
    error_log('[I-AMU] Uncaught ' . $e::class . ': ' . $e->getMessage()
        . ' @ ' . $e->getFile() . ':' . $e->getLine());
    (new ErrorController())->show(500, $e);
}
?>