<?php

namespace Core;

abstract class controller {


    /**
     * Rend une vue dans un layout.
     * Exemple : $this->render('sessions/show', ['session' => $s])
     *           → charge Views/sessions/show.php dans Views/layouts/main.php
     * La vue reçoit $data en extraction + la variable $layout.
     * Le layout reçoit $content (HTML de la vue) + les mêmes données.
     * @param array<string, mixed> $data
     */

    protected function render(
        string $view,
        array  $data   = [],
        string $layout = 'main'
        ): void {

        $viewFile   = self::$viewsPath . '/' . $view . '.php';
        $layoutFile = self::$viewsPath . '/layouts/' . $layout . '.php';

        if (!is_file($viewFile)) {
            throw new \RuntimeException("Vue introuvable : {$viewFile}");
        }

        // end la vue dans un buffer
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Injecte le buffer dans le layout
        if ($layout !== 'none' && is_file($layoutFile)) {
            extract($data, EXTR_SKIP);   // variables disponibles dans le layout aussi
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /** Redirige vers une URL et stoppe l'exécution. */
    protected function redirect(string $url): never
    {
        header('Location: ' . $url, replace: true, response_code: 302);
        exit;
    }

    /**
     * Ajoute un message flash dans la session.
     * Types : 'success', 'error', 'warning', 'info'
     * La vue le consomme via flash_messages() (helper icons.php).
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Récupère un champ POST de manière sécurisée.
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }


}