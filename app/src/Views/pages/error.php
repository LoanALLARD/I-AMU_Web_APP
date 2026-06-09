<?php
/**
 * Generic error page used by Core\Controller::renderForbidden() and
 * any future "graceful failure" branch (404, 500, …).
 *
 * @var int    $code
 * @var string $title
 * @var string $message
 */
?>
<div class="page-empty">
    <div class="mono error-code"><?= htmlspecialchars((string) ($code ?? 404)) ?></div>
    <h1 class="error-title"><?= htmlspecialchars($title ?? 'Erreur') ?></h1>
    <p class="error-message"><?= htmlspecialchars($message ?? 'Une erreur est survenue.') ?></p>
    <a href="/" class="btn">Retour à l'accueil</a>
</div>
