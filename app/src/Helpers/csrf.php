<?php

declare(strict_types=1);

use Core\Csrf;

/**
 * Convenience wrapper around {@see Core\Csrf::field()}.
 *
 * Usage in a view:
 *     <form method="POST" action="...">
 *         <?= csrf_field() ?>
 *         ...
 *     </form>
 *
 * Defined here (and loaded by `bootstrap.php`) so views never have to import
 * the namespaced class.
 */
if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}
