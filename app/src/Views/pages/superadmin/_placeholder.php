<?php
/**
 * Placeholder body for a panel sub-page (navigation shell — no business logic
 * yet). Expects `$pageIcon`, `$pageTitle`, `$pageLead` (strings) in scope.
 */
?>
<div class="page-placeholder">
    <span class="page-placeholder-icon"><?= icon($pageIcon ?? 'clock', '', 30) ?></span>
    <h2><?= htmlspecialchars($pageTitle ?? '') ?></h2>
    <p><?= htmlspecialchars($pageLead ?? '') ?></p>
    <span class="badge-soon"><?= icon('clock', '', 14) ?> A venir</span>
</div>
