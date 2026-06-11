<?php
/**
 * Department-admin console header: title, admin identity, action and tab nav.
 *
 * @var array<string, mixed>|null $user
 * @var array{name:string, place_name:string}|null $department
 * @var string $activeNav  'requests' | 'users'
 */
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$department = $department ?? null;
$active = $activeNav ?? 'requests';
$nav = [
    'requests' => ['/department-admin', 'Demandes', 'alert-triangle'],
    'users' => ['/department-admin/users', 'Utilisateurs', 'users'],
    'models' => ['/department-admin/models', 'models', 'square']
];
?>
<div class="page-header">
    <div class="page-header-row">
        <h1>Administration</h1>
        <?php if ($department !== null): ?>
            <span class="badge badge-teacher"><?= icon('building', '', 13) ?>
                <?= htmlspecialchars($department['name']) ?></span>
        <?php endif; ?>
    </div>
    <div class="admin-identity-meta">
        Connecté en tant que
        <strong><?= htmlspecialchars($displayName !== '' ? $displayName : 'administrateur') ?></strong>
        <span class="badge badge-draft">admin de département</span>
    </div>
</div>

<nav class="tabs" aria-label="Administration">
    <?php foreach ($nav as $key => [$href, $label, $ico]): ?>
        <a href="<?= htmlspecialchars($href) ?>" class="tab<?= $key === $active ? ' is-active' : '' ?>" <?= $key === $active ? 'aria-current="page"' : '' ?>>
            <?= icon($ico, '', 15) ?>
            <?= htmlspecialchars($label) ?>
        </a>
    <?php endforeach; ?>
</nav>