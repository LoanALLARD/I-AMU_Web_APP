<?php

namespace App\Core;

/**
 * Contrôleur de base.
 * Fournit les méthodes de rendu de vues, de redirection et de vérification d'auth.
 */
abstract class Controller
{
    /**
     * Rend une vue avec un layout.
     *
     * @param string $view    Chemin de la vue (ex: 'pages/auth/login')
     * @param array  $data    Variables passées à la vue
     * @param string $layout  Layout à utiliser
     */
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        // Les variables sont accessibles dans la vue
        extract($data);

        // Capture du contenu de la vue
        ob_start();
        $viewPath = __DIR__ . '/../views/' . $view . '.php';
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("Vue introuvable : $viewPath");
        }
        require $viewPath;
        $content = ob_get_clean();

        // Rendu avec le layout
        $layoutPath = __DIR__ . '/../views/layout/' . $layout . '.php';
        if (file_exists($layoutPath)) {
            require $layoutPath;
        } else {
            echo $content;
        }
    }

    /**
     * Rend une vue sans layout (pour les fragments AJAX / HTMX).
     */
    protected function renderPartial(string $view, array $data = []): void
    {
        extract($data);
        require __DIR__ . '/../views/' . $view . '.php';
    }

    /**
     * Redirige vers une URL.
     */
    protected function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }

    /**
     * Retourne une réponse JSON.
     */
    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Vérifie si l'utilisateur est connecté, redirige sinon.
     */
    protected function requireAuth(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    /**
     * Vérifie si l'utilisateur a un rôle spécifique.
     */
    protected function requireRole(string $role): void
    {
        $this->requireAuth();
        $roles = $_SESSION['roles'] ?? [];

        if (!in_array($role, $roles, true)) {
            http_response_code(403);
            $this->render('pages/error', [
                'title'   => 'Accès refusé',
                'code'    => 403,
                'message' => "Vous n'avez pas les permissions nécessaires.",
            ]);
            exit;
        }
    }

    /**
     * Vérifie si l'utilisateur a AU MOINS UN des rôles donnés.
     */
    protected function requireAnyRole(array $allowedRoles): void
    {
        $this->requireAuth();
        $roles = $_SESSION['roles'] ?? [];

        if (empty(array_intersect($allowedRoles, $roles))) {
            http_response_code(403);
            $this->render('pages/error', [
                'title'   => 'Accès refusé',
                'code'    => 403,
                'message' => "Vous n'avez pas les permissions nécessaires.",
            ]);
            exit;
        }
    }

    /**
     * Vérifie le consentement RGPD.
     */
    protected function requireGdprConsent(): void
    {
        $this->requireAuth();
        if (empty($_SESSION['gdpr_consent'])) {
            $this->redirect('/gdpr/consent');
        }
    }

    /**
     * Récupère l'utilisateur connecté depuis la session.
     */
    protected function currentUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id'         => $_SESSION['user_id'],
            'email'      => $_SESSION['user_email'] ?? '',
            'first_name' => $_SESSION['user_first_name'] ?? '',
            'last_name'  => $_SESSION['user_last_name'] ?? '',
            'roles'      => $_SESSION['roles'] ?? [],
        ];
    }

    /**
     * Récupère un champ POST de manière sécurisée.
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Récupère un paramètre GET.
     */
    protected function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Stocke un message flash en session.
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }
}
