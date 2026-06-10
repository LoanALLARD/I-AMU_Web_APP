<?php
/**
 * Department-admin console header: identity card, actions and the tab nav.
 *
 * @var array<string, mixed>|null $user
 * @var array{name:string, place_name:string}|null $department
 * @var string $activeNav  'requests' | 'users'
 */
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$department = $department ?? null;
$active = $activeNav ?? 'requests';
$nav = [
    'requests' => ['/department-admin',       'Demandes', 'alert-triangle'],
    'users'    => ['/department-admin/users',  'Utilisateurs', 'users'],
];
?>
<div class="page-header">
    <div class="page-header-row">
        <h1>Administration</h1>
        <?php if ($department !== null): ?>
            <span class="badge badge-teacher"><?= icon('building', '', 13) ?> <?= htmlspecialchars($department['name']) ?></span>
        <?php endif; ?>
    </div>
    <p class="page-sub">Espace d'administration de votre département.</p>
</div>

<div class="admin-section">
    <div class="admin-identity">
        <span class="admin-identity-icon"><?= icon('user', '', 18) ?></span>
        <div>
            <div class="admin-identity-name">
                Connecté en tant que <strong><?= htmlspecialchars($displayName !== '' ? $displayName : 'administrateur') ?></strong>
                <span class="badge badge-draft" style="margin-left:6px;">admin de département</span>
            </div>
            <div class="admin-identity-meta">
                <?= icon('message-circle', '', 12) ?> <?= htmlspecialchars($user['email'] ?? '') ?>
                <?php if ($department !== null): ?>
                    &middot; <?= icon('building', '', 12) ?> département <?= htmlspecialchars($department['name']) ?>
                    &middot; <?= icon('graduation-cap', '', 12) ?> <?= htmlspecialchars($department['place_name']) ?>
                <?php endif; ?>
            </div>
        </div>
        <a href="/department-admin/addModel" class="btn sm" style="margin-left:auto;">
            <?= icon('plus', '', 13) ?> ajouter un model
        </a>
    </div>
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
