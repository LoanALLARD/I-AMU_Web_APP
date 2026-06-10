<?php
/**
 * Researcher space chrome: page header (title, role badge, identity line)
 * plus the sub-navigation tabs. Shared by every researcher page so the two
 * never drift apart.
 *
 * Required in the including scope:
 * @var array{first_name?:string, last_name?:string, email?:string}|null $user
 * @var string $activeTab  'access', 'analysis' or 'export'.
 */
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$activeTab   = $activeTab ?? 'access';
?>
<div class="page-header">
    <div class="page-header-row">
        <h1>Espace chercheur</h1>
        <span class="badge badge-draft"><?= icon('flask-conical', '', 13) ?> chercheur</span>
    </div>
    <div class="admin-identity-meta">
        Connecté en tant que <strong><?= htmlspecialchars($displayName !== '' ? $displayName : 'chercheur') ?></strong>
    </div>
</div>

<nav class="tabs" aria-label="Espace chercheur">
    <a href="/researcher" class="tab<?= $activeTab === 'access' ? ' is-active' : '' ?>"<?= $activeTab === 'access' ? ' aria-current="page"' : '' ?>>
        <?= icon('key-round', '', 15) ?> Mes accès
    </a>
    <a href="/researcher/data" class="tab<?= $activeTab === 'analysis' ? ' is-active' : '' ?>"<?= $activeTab === 'analysis' ? ' aria-current="page"' : '' ?>>
        <?= icon('chart-line', '', 15) ?> Analyse
    </a>
    <a href="/researcher/export" class="tab<?= $activeTab === 'export' ? ' is-active' : '' ?>"<?= $activeTab === 'export' ? ' aria-current="page"' : '' ?>>
        <?= icon('download', '', 15) ?> Export
    </a>
</nav>
