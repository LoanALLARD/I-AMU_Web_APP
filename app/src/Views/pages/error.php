<?php
/**
 * Generic error page (404 / 403 / 500 …), rendered by ErrorController and by
 * Core\Controller::renderForbidden(). Shows the status code, a friendly
 * adapted message (or an explicit exception message), and - only when
 * APP_DEBUG is on - the technical details.
 *
 * @var int                                                              $code
 * @var string                                                           $title
 * @var string                                                           $message
 * @var array{type:string,msg:string,where:string,trace:string}|null     $exception
 */
$exception = $exception ?? null;
?>
<div class="error-page">
    <div class="error-code mono"><?= htmlspecialchars((string) ($code ?? 404)) ?></div>
    <h1 class="error-title"><?= htmlspecialchars($title ?? 'Erreur') ?></h1>
    <p class="error-message"><?= htmlspecialchars($message ?? 'Une erreur est survenue.') ?></p>

    <div class="error-actions">
        <a href="/" class="btn primary">Retour à l'accueil</a>
    </div>

    <?php if ($exception !== null): ?>
        <details class="error-debug">
            <summary>Détails techniques (mode debug)</summary>
            <div class="error-debug-body">
                <p><strong><?= htmlspecialchars($exception['type']) ?></strong>
                    <?= $exception['msg'] !== '' ? '- ' . htmlspecialchars($exception['msg']) : '' ?></p>
                <p class="mono error-debug-where"><?= htmlspecialchars($exception['where']) ?></p>
                <pre><?= htmlspecialchars($exception['trace']) ?></pre>
            </div>
        </details>
    <?php endif; ?>
</div>
