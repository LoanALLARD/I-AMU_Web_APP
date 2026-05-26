<?php

/**
 * I-AMU - Définition des routes.
 * Variable $router disponible via Application::loadRoutes().
 */

use App\Controllers\HomeController;
use App\Controllers\LoginController;
use App\Controllers\ChatController;
use App\Controllers\SessionController;
use App\Controllers\GdprController;
use App\Controllers\ExportController;
use App\Controllers\ExamController;
use App\Controllers\AdminController;
use App\Controllers\AccountController;

// ─── Page d'accueil ────────────────────────────────────────
$router->get('/',          HomeController::class, 'index');

// ─── Authentification ──────────────────────────────────────
$router->get('/login',     LoginController::class, 'showLogin');
$router->post('/login',    LoginController::class, 'login');
$router->get('/register',  LoginController::class, 'showRegister');
$router->post('/register', LoginController::class, 'register');
$router->get('/logout',    LoginController::class, 'logout');

// ─── RGPD ──────────────────────────────────────────────────
$router->get('/gdpr/consent',  GdprController::class, 'showConsent');
$router->post('/gdpr/consent', GdprController::class, 'handleConsent');

// ─── Chat (mode libre) ────────────────────────────────────
$router->get('/chat',               ChatController::class, 'index');
$router->post('/chat/create',       ChatController::class, 'createConversation');
$router->post('/chat/send',         ChatController::class, 'sendPrompt');
$router->post('/chat/stream',       ChatController::class, 'sendPromptStream');
$router->get('/chat/ollama/status', ChatController::class, 'ollamaStatus');
$router->post('/chat/{id}/archive', ChatController::class, 'archive');
$router->get('/chat/{id}',          ChatController::class, 'show');

// ─── Sessions (cours / examens) ───────────────────────────
$router->get('/sessions',            SessionController::class, 'index');
$router->get('/sessions/create',     SessionController::class, 'create');
$router->post('/sessions/store',     SessionController::class, 'store');
$router->get('/sessions/{id}',       SessionController::class, 'dashboard');
$router->post('/sessions/join',      SessionController::class, 'join');

// ─── Mode Examen (interface verrouillée) ──────────────────
$router->get('/exam/{id}',            ExamController::class, 'show');
$router->post('/exam/send',           ExamController::class, 'sendPrompt');
$router->get('/exam/{id}/supervise',  ExamController::class, 'supervise');
$router->get('/exam/{id}/poll',       ExamController::class, 'pollInteractions');

// ─── Administration ───────────────────────────────────────
$router->get('/admin',               AdminController::class, 'index');
$router->get('/admin/users',         AdminController::class, 'users');
$router->post('/admin/users/role',   AdminController::class, 'updateRole');
$router->get('/admin/models',        AdminController::class, 'models');
$router->post('/admin/models/store', AdminController::class, 'storeModel');
$router->post('/admin/models/toggle', AdminController::class, 'toggleModel');
$router->post('/admin/models/sync',   AdminController::class, 'syncModels');
$router->get('/admin/config',        AdminController::class, 'config');

// ─── Compte utilisateur ──────────────────────────────────
$router->get('/account',                AccountController::class, 'index');
$router->post('/account/profile',       AccountController::class, 'updateProfile');
$router->post('/account/password',      AccountController::class, 'changePassword');
$router->post('/account/revoke-consent', AccountController::class, 'revokeConsent');
$router->post('/account/delete',        AccountController::class, 'deleteAccount');

// ─── Export données (chercheur) ───────────────────────────
$router->get('/export',      ExportController::class, 'index');
$router->get('/export/json', ExportController::class, 'exportJson');
