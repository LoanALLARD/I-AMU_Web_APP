<?php

/**
 * Flash messages. Renders then consumes $_SESSION['_flash'] (populated by
 * Core\Controller::flash()). Included once per response: by layout/chat.php for
 * authenticated pages, and directly by the auth / super-admin login pages whose
 * layouts have no flash slot.
 */

if (empty($_SESSION['_flash'])) {
    return;
}
?>
<div class="flash-stack">
    <?php foreach ($_SESSION['_flash'] as $flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endforeach; ?>
</div>
<?php unset($_SESSION['_flash']); ?>
