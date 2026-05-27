<?php

/**
 * Mappe deux préfixes de namespace :
 *   - App\                  → app/
 *   - PHPMailer\PHPMailer\  → vendor/phpmailer/phpmailer/src/
 *
 * Pour chaque classe demandée, on essaie deux variantes du chemin :
 *   1. Chemin "strict PSR-4" (PascalCase, ex: app/Controllers/AdminController.php)
 *   2. Chemin "legacy" avec les dossiers en lowercase (ex: app/controllers/AdminController.php)
 *
 */

spl_autoload_register(static function (string $class): bool {
    /** @var array<string, string> $prefixes Préfixe de namespace -> dossier de base */
    static $prefixes = null;

    if ($prefixes === null) {
        $root = dirname(__DIR__);
        $prefixes = [
            'App\\' => $root . '/app',
            'PHPMailer\\PHPMailer\\' => $root . '/vendor/phpmailer/phpmailer/src',
        ];
    }

    foreach ($prefixes as $prefix => $baseDir) {
        // Le namespace doit commencer par le préfixe (strncmp = strict & rapide)
        $prefixLen = strlen($prefix);
        if (strncmp($class, $prefix, $prefixLen) !== 0) {
            continue;
        }

        // Reste du namespace après le préfixe -> composantes de chemin
        $relative = substr($class, $prefixLen);
        $segments = explode('\\', $relative);
        $fileName = array_pop($segments) . '.php';   // ex: "Application.php"

        // 1ere tentative : dossiers tels quels (PascalCase)
        $strictPath = $baseDir
            . (empty($segments) ? '' : '/' . implode('/', $segments))
            . '/' . $fileName;
        if (is_file($strictPath)) {
            require_once $strictPath;
            return true;
        }

        // 2eme tentative : dossiers en lowercase (legacy, structure actuelle du projet)
        $lowerSegments = array_map('strtolower', $segments);
        $legacyPath = $baseDir
            . (empty($lowerSegments) ? '' : '/' . implode('/', $lowerSegments))
            . '/' . $fileName;
        if (is_file($legacyPath)) {
            require_once $legacyPath;
            return true;
        }
    }

    return false;
});
