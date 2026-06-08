<?php
/**
 * Researcher space chrome: page header (title, role badge, identity line)
 * plus the sub-navigation tabs. Shared by every researcher page so the two
 * never drift apart.
 *
 * Required in the including scope:
 * @var array{first_name?:string, last_name?:string, email?:string}|null $user
 * @var string $activeTab  'access' or 'data'.
 */
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$activeTab   = $activeTab ?? 'access';
?>
<div class="page-header">
    <div class="page-header-row">
        <h1>Espace chercheur</h1>
        <span class="badge badge-draft"><?= icon('flask-conical', '', 13) ?> chercheur</span>
    </div>
    <p class="page-sub">Connecte en tant que <strong><?= htmlspecialchars($displayName !== '' ? $displayName : 'chercheur') ?></strong> &middot; <?= htmlspecialchars($user['email'] ?? '') ?></p>
</div>

<nav class="tabs" aria-label="Espace chercheur">
    <a href="/researcher" class="tab<?= $activeTab === 'access' ? ' is-active' : '' ?>"<?= $activeTab === 'access' ? ' aria-current="page"' : '' ?>>
        <?= icon('key-round', '', 15) ?> Mes acces
    </a>
    <a href="/researcher/data" class="tab<?= $activeTab === 'data' ? ' is-active' : '' ?>"<?= $activeTab === 'data' ? ' aria-current="page"' : '' ?>>
        <?= icon('database', '', 15) ?> Donnees &amp; export
    </a>
</nav>
