<?php

declare(strict_types=1);

namespace Core;

/**
 * Base controller for every HTTP controller (legacy and new).
 *
 * Provides view rendering, redirection, JSON responses, flash messages,
 * auth helpers (single role / any-of), CSRF verification and convenient
 * accessors for POST / GET fields.
 */
abstract class Controller
{
    /** @var string Absolute path to the Views directory. */
    private static string $viewsPath = __DIR__ . '/../Views';

    // ----------------------------------------------------------------
    // Rendering
    // ----------------------------------------------------------------

    /**
     * Renders a view inside a layout.
     * Example: $this->render('pages/auth/login', ['email' => $email])
     *
     * IMPORTANT — the parameter is named `$template` (not `$view`) so a
     * `'view' => ...` key in `$data` can extract correctly. With `$view`
     * as the parameter name, EXTR_SKIP would silently keep the path
     * string instead of injecting the view-model, producing broken pages
     * (notably the Session dashboard which passes `'view' => $dashboardVm`).
     *
     * @param array<string, mixed> $data Variables passed to the view
     */
    protected function render(
        string $template,
        array $data = [],
        string $layout = 'main'
    ): void {

        $viewFile = self::$viewsPath . '/' . $template . '.php';
        $layoutFile = self::$viewsPath . '/layout/' . $layout . '.php';

        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: {$viewFile}");
        }

        // Render the view body into a string so the layout can inject it.
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout !== 'none' && is_file($layoutFile)) {
            extract($data, EXTR_SKIP);
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /**
     * Renders a view without any layout — useful for AJAX fragments.
     *
     * @param array<string, mixed> $data
     */
    protected function renderPartial(string $template, array $data = []): void
    {
        $viewFile = self::$viewsPath . '/' . $template . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: {$viewFile}");
        }
        extract($data, EXTR_SKIP);
        require $viewFile;
    }

    /**
     * Renders a partial into a string instead of echoing it. Lets an action
     * return server-rendered HTML (CSRF, icons, escaping all done by PHP) in a
     * JSON payload, so the front never has to rebuild markup itself.
     *
     * @param array<string, mixed> $data
     */
    protected function capturePartial(string $template, array $data = []): string
    {
        ob_start();
        $this->renderPartial($template, $data);
        return (string) ob_get_clean();
    }

    /**
     * Emits a JSON response and terminates the request.
     */
    protected function json(mixed $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    // ----------------------------------------------------------------
    // Redirects + flash + IO accessors
    // ----------------------------------------------------------------

    /** Redirects to a URL and stops execution. */
    protected function redirect(string $url): never
    {
        header('Location: ' . $url, replace: true, response_code: 302);
        exit;
    }

    /**
     * Stores a flash message. Types: 'success', 'error', 'warning', 'info'.
     * Read once on the next request through `$_SESSION['_flash']`.
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** Returns a POST field, falling back to $default if absent. */
    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /** Returns a GET field, falling back to $default if absent. */
    protected function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Tells whether the request expects a JSON response (fetch/XHR), so an
     * action can answer with json() instead of redirecting. The front sets
     * the X-Requested-With header on its AJAX calls.
     */
    protected function wantsJson(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    // ----------------------------------------------------------------
    // Auth + CSRF
    // ----------------------------------------------------------------

    /**
     * Ensures the visitor is authenticated. Redirects to /login otherwise.
     */
    protected function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    /**
     * Non-blocking role check: tells whether the current user carries the
     * given role. Use this to branch (redirect, show a link); use
     * requireRole() when the role is mandatory to proceed.
     */
    protected function hasRole(string $role): bool
    {
        return in_array($role, $_SESSION['roles'] ?? [], true);
    }

    /**
     * Ensures the authenticated user has the given role. Renders a 403
     * error page otherwise.
     */
    protected function requireRole(string $role): void
    {
        $this->requireAuth();
        $roles = $_SESSION['roles'] ?? [];
        if (!in_array($role, $roles, true)) {
            $this->renderForbidden();
        }
    }

    /**
     * Ensures the authenticated user has AT LEAST ONE of the given roles.
     *
     * @param list<string> $allowedRoles
     */
    protected function requireAnyRole(array $allowedRoles): void
    {
        $this->requireAuth();
        $roles = $_SESSION['roles'] ?? [];
        if (array_intersect($allowedRoles, $roles) === []) {
            $this->renderForbidden();
        }
    }

    /**
     * Verifies the submitted CSRF token. Aborts with 419 + flash on failure
     * so the routing tree doesn't proceed with a poisoned request.
     */
    protected function verifyCsrf(): void
    {
        if (!Csrf::verify()) {
            http_response_code(419);
            $this->flash('error', 'Session expirée, merci de réessayer.');
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    /**
     * Returns the currently logged-in user as a flat array, or null.
     *
     * @return array{id:int, email:string, first_name:string, last_name:string, roles:list<string>, department_id:int|null}|null
     */
    protected function currentUser(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id' => (int) $_SESSION['user_id'],
            'email' => (string) ($_SESSION['user_email'] ?? ''),
            'first_name' => (string) ($_SESSION['user_first_name'] ?? ''),
            'last_name' => (string) ($_SESSION['user_last_name'] ?? ''),
            'roles' => $_SESSION['roles'] ?? [],
            'theme' => $_SESSION['user_theme'] ?? null,
            'department_id' => isset($_SESSION['user_department_id'])
                ? (int) $_SESSION['user_department_id']
                : null,
        ];
    }

    protected function renderForbidden(): never
    {
        http_response_code(403);
        $this->render('pages/error', [
            'title' => 'Accès refusé',
            'code' => 403,
            'message' => "Vous n'avez pas les permissions nécessaires pour accéder à cette page.",
        ]);
        exit;
    }
}
