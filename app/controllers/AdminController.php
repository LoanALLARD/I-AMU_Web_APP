<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\User;
use App\Models\LlmModel;

class AdminController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Page d'administration principale.
     */
    public function index(): void
    {
        $this->requireRole('admin');

        $db = Database::getInstance();

        // Statistiques globales
        $stats = [
            'total_users'         => $this->userModel->count(),
            'total_students'      => $this->countTable('student'),
            'total_teachers'      => $this->countTable('teacher'),
            'total_researchers'   => $this->countTable('researcher'),
            'total_admins'        => $this->countTable('administrator'),
            'total_conversations' => $this->countTable('conversation'),
            'total_interactions'  => $this->countTable('interaction'),
            'total_sessions'      => $this->countTable('session'),
        ];

        $this->render('pages/admin/index', [
            'title' => 'Administration',
            'stats' => $stats,
            'user'  => $this->currentUser(),
        ]);
    }

    /**
     * Gestion des utilisateurs et rôles.
     */
    public function users(): void
    {
        $this->requireRole('admin');

        $db = Database::getInstance();
        $page = max(1, (int) $this->query('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $search = trim($this->query('search', ''));

        $sql = 'SELECT u.*, 
                    EXISTS(SELECT 1 FROM student s WHERE s.user_id = u.user_id) AS is_student,
                    EXISTS(SELECT 1 FROM teacher t WHERE t.user_id = u.user_id) AS is_teacher,
                    (SELECT t.is_specialised FROM teacher t WHERE t.user_id = u.user_id) AS is_specialised,
                    EXISTS(SELECT 1 FROM researcher r WHERE r.user_id = u.user_id) AS is_researcher,
                    EXISTS(SELECT 1 FROM administrator a WHERE a.user_id = u.user_id) AS is_admin
                FROM "user" u';

        $params = [];
        if ($search) {
            $sql .= ' WHERE u.email ILIKE :search OR u.first_name ILIKE :search OR u.last_name ILIKE :search';
            $params['search'] = "%$search%";
        }

        $sql .= ' ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset';
        $params['limit'] = $perPage;
        $params['offset'] = $offset;

        // PDO ne gère pas LIMIT/OFFSET avec les paramètres nommés en mode émulation désactivée
        // On construit la requête directement
        $sqlDirect = str_replace([':limit', ':offset'], [$perPage, $offset], $sql);
        unset($params['limit'], $params['offset']);

        $users = $db->query($sqlDirect, $params)->fetchAll();
        $total = $this->userModel->count();

        $this->render('pages/admin/users', [
            'title'   => 'Gestion des utilisateurs',
            'users'   => $users,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'search'  => $search,
            'user'    => $this->currentUser(),
        ]);
    }

    /**
     * Met à jour les rôles d'un utilisateur (POST AJAX).
     */
    public function updateRole(): void
    {
        $this->requireRole('admin');

        $userId = (int) $this->input('user_id');
        $role   = $this->input('role');
        $action = $this->input('action'); // 'add' ou 'remove'

        if (!$userId || !$role || !in_array($action, ['add', 'remove'])) {
            $this->json(['error' => 'Paramètres invalides.'], 400);
            return;
        }

        $db = Database::getInstance();

        try {
            if ($action === 'add') {
                $this->addRole($db, $userId, $role);
            } else {
                $this->removeRole($db, $userId, $role);
            }
            $this->json(['success' => true, 'roles' => $this->userModel->getRoles($userId)]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Page de configuration de l'application.
     */
    public function config(): void
    {
        $this->requireRole('admin');

        $config = \App\Core\Application::getInstance()->getConfig();

        $this->render('pages/admin/config', [
            'title'       => 'Configuration',
            'appConfig'   => $config,
            'user'        => $this->currentUser(),
        ]);
    }

    /**
     * Gestion des modèles LLM.
     */
    public function models(): void
    {
        $this->requireRole('admin');

        $llmModel = new LlmModel();
        $models = $llmModel->findAll('name ASC');

        // Vérifier la disponibilité d'Ollama
        $ollamaService = new \App\Services\OllamaService();
        $ollamaAvailable = $ollamaService->isAvailable();
        $ollamaModels = $ollamaAvailable ? $ollamaService->listModels() : [];

        $this->render('pages/admin/models', [
            'title'            => 'Gestion des modèles LLM',
            'models'           => $models,
            'ollamaAvailable'  => $ollamaAvailable,
            'ollamaModels'     => $ollamaModels,
            'user'             => $this->currentUser(),
        ]);
    }

    /**
     * Ajoute ou met à jour un modèle LLM (POST).
     */
    public function storeModel(): void
    {
        $this->requireRole('admin');

        $llmModel = new LlmModel();
        $llmModel->create([
            'name'           => trim($this->input('name', '')),
            'version'        => trim($this->input('version', '')),
            'provider'       => trim($this->input('provider', 'ollama')),
            'max_tokens'     => (int) $this->input('max_tokens', 4096),
            'context_window' => (int) $this->input('context_window', 8192),
            'is_active'      => (bool) $this->input('is_active', true),
        ]);

        $this->flash('success', 'Modèle ajouté avec succès.');
        $this->redirect('/admin/models');
    }

    /**
     * Active/désactive un modèle (POST AJAX).
     */
    public function toggleModel(): void
    {
        $this->requireRole('admin');

        $modelId = (int) $this->input('model_id');
        $active  = (bool) $this->input('is_active');

        $llmModel = new LlmModel();
        $llmModel->update($modelId, ['is_active' => $active]);

        $this->json(['success' => true]);
    }

    /**
     * Synchronise la table `model` avec les modèles disponibles sur Ollama.
     * Forcé manuellement par l'admin via un POST (réinitialise le cache).
     */
    public function syncModels(): void
    {
        $this->requireRole('admin');

        $ollama = new \App\Services\OllamaService();
        if (!$ollama->isAvailable()) {
            $this->flash('error', 'Ollama est injoignable. Vérifiez que le serveur tourne.');
            $this->redirect('/admin/models');
            return;
        }

        try {
            $result = (new LlmModel())->syncFromOllama($ollama);
            // Invalide le cache de sync auto du ChatController
            @unlink(sys_get_temp_dir() . '/iamu-ollama-sync.lock');

            $msg = sprintf(
                '%d ajouté(s), %d réactivé(s), %d désactivé(s).',
                $result['added'], $result['reactivated'], $result['disabled']
            );
            $this->flash('success', "Synchronisation Ollama terminée : $msg");
        } catch (\Exception $e) {
            $this->flash('error', 'Erreur de synchronisation : ' . $e->getMessage());
        }

        $this->redirect('/admin/models');
    }

    // ─── Helpers privés ─────────────────────────────────────

    private function addRole(Database $db, int $userId, string $role): void
    {
        $queries = [
            'student'             => 'INSERT INTO student (user_id, student_number) VALUES (:uid, NULL) ON CONFLICT DO NOTHING',
            'teacher'             => 'INSERT INTO teacher (user_id, is_specialised, title) VALUES (:uid, false, NULL) ON CONFLICT DO NOTHING',
            'teacher_specialised' => 'UPDATE teacher SET is_specialised = true WHERE user_id = :uid',
            'researcher'          => 'INSERT INTO researcher (user_id, laboratory) VALUES (:uid, NULL) ON CONFLICT DO NOTHING',
            'admin'               => 'INSERT INTO administrator (user_id, is_super_admin) VALUES (:uid, false) ON CONFLICT DO NOTHING',
        ];

        if (!isset($queries[$role])) {
            throw new \InvalidArgumentException("Rôle inconnu : $role");
        }

        // Pour "spécialisé", s'assurer que la personne est d'abord enseignante
        if ($role === 'teacher_specialised') {
            $isTeacher = $db->query('SELECT 1 FROM teacher WHERE user_id = :uid', ['uid' => $userId])->fetch();
            if (!$isTeacher) {
                $db->query($queries['teacher'], ['uid' => $userId]);
            }
        }

        $db->query($queries[$role], ['uid' => $userId]);
    }

    private function removeRole(Database $db, int $userId, string $role): void
    {
        $queries = [
            'student'             => 'DELETE FROM student WHERE user_id = :uid',
            'teacher'             => 'DELETE FROM teacher WHERE user_id = :uid',
            'teacher_specialised' => 'UPDATE teacher SET is_specialised = false WHERE user_id = :uid',
            'researcher'          => 'DELETE FROM researcher WHERE user_id = :uid',
            'admin'               => 'DELETE FROM administrator WHERE user_id = :uid',
        ];

        if (!isset($queries[$role])) {
            throw new \InvalidArgumentException("Rôle inconnu : $role");
        }

        $db->query($queries[$role], ['uid' => $userId]);
    }

    private function countTable(string $table): int
    {
        $result = Database::getInstance()->query("SELECT COUNT(*) as c FROM \"$table\"")->fetch();
        return (int) $result['c'];
    }
}
