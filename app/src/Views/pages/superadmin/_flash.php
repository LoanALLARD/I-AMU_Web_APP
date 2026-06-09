<?php /** Renders and clears pending flash messages. Placed inside the content area. */ ?>
<?php if (!empty($_SESSION['_flash'])): ?>
    <?php foreach ($_SESSION['_flash'] as $flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endforeach; ?>
    <?php unset($_SESSION['_flash']); ?>
<?php endif; ?>
