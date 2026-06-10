<?php
/** Panel header (title bar + nav). Expects `$superAdmin` and `$activeNav` in scope. */
$nav = [
    'department-admins' => ['/super-admin/department-admins', 'Administrateurs', 'users'],
    'places'            => ['/super-admin/places',            'Sites & départements', 'building'],
    'email-domains'     => ['/super-admin/email-domains',     'Domaines email', 'settings'],
];
$active = $activeNav ?? 'department-admins';
?>
<header class="dashboard-header">
    <div class="dashboard-header-inner">
        <div>
            <h1>Panel super administrateur</h1>
            <?php if (!empty($superAdmin)): ?>
                <p class="panel-greeting">
                    Connecté en tant que
                    <?= htmlspecialchars(trim(($superAdmin['first_name'] ?? '') . ' ' . ($superAdmin['last_name'] ?? ''))) ?>
                </p>
            <?php endif; ?>
        </div>

        <form method="POST" action="/super-admin/logout" class="logout-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn-logout">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Se déconnecter
            </button>
        </form>
    </div>

    <nav class="dashboard-nav">
        <div class="dashboard-nav-inner">
            <?php foreach ($nav as $key => [$href, $label, $ico]): ?>
                <a href="<?= htmlspecialchars($href) ?>"
                   class="nav-link<?= $key === $active ? ' is-active' : '' ?>"
                   <?= $key === $active ? 'aria-current="page"' : '' ?>>
                    <?= icon($ico, '', 16) ?>
                    <?= htmlspecialchars($label) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>
