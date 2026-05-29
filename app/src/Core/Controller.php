<?php

namespace Core;

/**
 * Base controller for all application controllers.
 * Provides rendering, redirection, flash messages and auth helpers.
 */
abstract class Controller {

    /** @var string Absolute path to the Views directory. */
    private static string $viewsPath = __DIR__ . '/../Views';

    /**
     * Renders a view inside a layout.
     * Example: $this->render('auth/login', ['email' => $email])
     *
     * @param array<string, mixed> $data Variables passed to the view
     */

    protected function render(
        string $view,
        array  $data   = [],
        string $layout = 'main'
        ): void {

        $viewFile   = self::$viewsPath . '/' . $view . '.php';
        $layoutFile = self::$viewsPath . '/Layouts/' . $layout . '.php';

        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found : {$viewFile}");
        }

        // Render the view into a buffer
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Inject the buffer into the layout
        if ($layout !== 'none' && is_file($layoutFile)) {
            extract($data, EXTR_SKIP);   // variables disponibles dans le layout aussi
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /** Redirects to a URL and stops execution. */
    protected function redirect(string $url): never
    {
        header('Location: ' . $url, replace: true, response_code: 302);
        exit;
    }

    /**
     * Adds a flash message to the session.
     * Types: 'success', 'error', 'warning', 'info'
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** Retrieves a POST field safely. */
    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }
    /**
     * Ensures the user is logged in.
     * Redirects to /login if not authenticated.
     */
    protected function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    /**
     * Returns the currently logged-in user data from session, or null.
     * @return array<string, mixed>|null
     */
    protected function currentUser(): ?array
    {
        if (empty($_SESSION['user_id'])) {
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

}