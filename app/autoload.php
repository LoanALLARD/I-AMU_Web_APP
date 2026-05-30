<?php

declare(strict_types=1);

/**
 * I-AMU hand-written autoloader.
 *
 * Used at RUNTIME by public/index.php. The Composer-generated
 * vendor/autoload.php is reserved for development tools only
 * (PHPStan, PHPUnit, PHP_CodeSniffer) — that is why app/composer.json
 * still declares an "autoload" section: it feeds these tools.
 *
 * Namespace mapping mirrors app/composer.json so behaviour is
 * identical between the two autoloaders:
 *   - Src\         → app/src/
 *   - Controllers\ → app/src/Controllers/
 *   - Core\        → app/src/Core/
 *
 * Pure PHP, no dependency.
 */

spl_autoload_register(static function (string $class): bool {
    /** @var array<string, string>|null $prefixes FQCN prefix -> base dir. */
    static $prefixes = null;

    if ($prefixes === null) {
        // __DIR__ is app/. Mapping mirrors composer.json's psr-4
        // section so we don't drift between the two autoloaders.
        $appRoot = __DIR__;
        $prefixes = [
            'Controllers\\' => $appRoot . '/src/Controllers',
            'Core\\'        => $appRoot . '/src/Core',
            'Models\\'      => $appRoot . '/src/Models',
            'Services\\'    => $appRoot . '/src/Services',
            'Src\\'         => $appRoot . '/src',
            'Data\\'        => $appRoot . '/src/Data',
        ];
    }

    foreach ($prefixes as $prefix => $baseDir) {
        $prefixLen = strlen($prefix);
        if (strncmp($class, $prefix, $prefixLen) !== 0) {
            continue;
        }

        // Remainder after the prefix → file path under $baseDir.
        $relative = substr($class, $prefixLen);
        $path     = $baseDir . '/' . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require_once $path;
            return true;
        }
    }

    return false;
});
