<?php
/**
 * Teacher's resource + session tabbed list.
 *
 * @var list<array<string, mixed>> $ressources
 * @var list<array<string, mixed>> $sessions
 * @var string                     $navSection  'ressources' | 'sessions'
 * @var array<string, mixed>       $user
 */

$activeTab = $navSection === 'sessions' ? 'sessions' : 'ressources';

$stateLabels = [
    'DRAFT'     => ['label' => 'Brouillon', 'class' => 'badge-draft'],
    'PUBLISHED' => ['label' => 'Publiée',   'class' => 'badge-active'],
    'ARCHIVED'  => ['label' => 'Archivée',  'class' => 'badge-archived'],
];

?>

<div class="page-header">
    <div class="page-header-row">
        <?php if ($activeTab === 'ressources'): ?>
            <h1 class="page-title"><?= icon('book', '', 18) ?> Mes ressources</h1>
            <a href="/ressources/create" class="btn primary">
                <?= icon('plus', '', 14) ?> Nouvelle ressource
            </a>
        <?php else: ?>
            <h1 class="page-title"><?= icon('graduation-cap', '', 18) ?> Mes sessions</h1>
            <a href="/sessions/create" class="btn primary">
                <?= icon('plus', '', 14) ?> Nouvelle session
            </a>
        <?php endif; ?>

    </div>
    <?php if ($activeTab === 'ressources'): ?>
        <p class="page-sub">Les ressources représentent vos cours. Une session doit être rattachée à une ressource.</p>
    <?php else: ?>
        <p class="page-sub">Les sessions représentent les instances de vos cours. Chaque session doit être rattachée à une ressource.</p>
    <?php endif; ?>
</div>

<!-- Onglets -->
<div class="tab-bar">
    <a href="/ressources"
       class="tab-item<?= $activeTab === 'ressources' ? ' is-active' : '' ?>">
        <?= icon('book', '', 14) ?>
        Mes ressources
        <span class="tab-count"><?= count($ressources) ?></span>
    </a>
    <a href="/sessions"
       class="tab-item<?= $activeTab === 'sessions' ? ' is-active' : '' ?>">
        <?= icon('graduation-cap', '', 14) ?>
        Mes sessions
        <span class="tab-count"><?= count($sessions) ?></span>
    </a>
</div>

<div class="page-body">

<?php if ($activeTab === 'ressources'): ?>

    <?php if ($ressources === []): ?>
        <div class="session-empty">
            <p>Aucune ressource pour le moment.</p>
            <p style="font-size:13px;color:var(--gray-400)">Créez votre première ressource pour pouvoir l'associer à une session.</p>
            <a href="/ressources/create" class="btn primary" style="margin-top:8px">
                <?= icon('plus', '', 14) ?> Créer une ressource
            </a>
        </div>
    <?php else: ?>
        <div class="session-table-wrap">
            <table class="session-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Semestre</th>
                        <th>État</th>
                        <th>Accès</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ressources as $r):
                        $state     = (string) ($r['state'] ?? 'DRAFT');
                        $stateMeta = $stateLabels[$state] ?? ['label' => $state, 'class' => 'badge-draft'];
                        $isOwner   = filter_var($r['is_owner'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    ?>
                    <tr>
                        <td><span style="font-family:var(--font-mono);font-weight:700;font-size:13px"><?= htmlspecialchars((string) $r['code']) ?></span></td>
                        <td>
                            <div style="font-weight:600;color:var(--gray-800)"><?= htmlspecialchars((string) $r['name']) ?></div>
                            <?php if (!empty($r['description'])): ?>
                                <div style="font-size:12px;color:var(--gray-400);margin-top:2px"><?= htmlspecialchars(mb_strimwidth((string) $r['description'], 0, 72, '…')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($r['semester'])): ?>
                                <span style="font-family:var(--font-mono);font-size:13px"><?= htmlspecialchars((string) $r['semester']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--gray-400)">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= htmlspecialchars($stateMeta['class']) ?>"><?= htmlspecialchars($stateMeta['label']) ?></span></td>
                        <td>
                            <?php if ($isOwner): ?>
                                <span class="badge badge-owner"><?= icon('user', '', 11) ?> Propriétaire</span>
                            <?php else: ?>
                                <span class="badge badge-shared"><?= icon('users', '', 11) ?> Partagée</span>
                            <?php endif; ?>
                        </td>
                        <td class="cell-actions">
                            <?php if ($isOwner): ?>
                                <a href="/ressources/<?= (int) $r['id'] ?>/edit" class="btn ghost sm"><?= icon('edit', '', 13) ?> Modifier</a>
                                <form method="POST" action="/ressources/<?= (int) $r['id'] ?>/delete" style="display:inline"
                                      onsubmit="return confirm('Supprimer « <?= htmlspecialchars((string) $r['name'], ENT_QUOTES) ?> » ? Cette action est irréversible.')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn ghost sm danger"><?= icon('trash', '', 13) ?> Supprimer</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="session-cards">
            <?php foreach ($ressources as $r):
                $state     = (string) ($r['state'] ?? 'DRAFT');
                $stateMeta = $stateLabels[$state] ?? ['label' => $state, 'class' => 'badge-draft'];
                $isOwner   = filter_var($r['is_owner'] ?? false, FILTER_VALIDATE_BOOLEAN);
            ?>
            <div class="session-card">
                <div class="session-card-head">
                    <span class="session-card-title"><?= htmlspecialchars((string) $r['name']) ?></span>
                    <span style="font-family:var(--font-mono);font-size:12px;color:var(--gray-400)"><?= htmlspecialchars((string) $r['code']) ?></span>
                </div>
                <div class="session-card-meta">
                    <span class="badge <?= htmlspecialchars($stateMeta['class']) ?>"><?= htmlspecialchars($stateMeta['label']) ?></span>
                    <?php if ($isOwner): ?>
                        <span class="badge badge-owner"><?= icon('user', '', 11) ?> Propriétaire</span>
                    <?php else: ?>
                        <span class="badge badge-shared"><?= icon('users', '', 11) ?> Partagée</span>
                    <?php endif; ?>
                </div>
                <?php if ($isOwner): ?>
                <div class="session-card-actions">
                    <a href="/ressources/<?= (int) $r['id'] ?>/edit" class="btn ghost sm"><?= icon('edit', '', 13) ?> Modifier</a>
                    <form method="POST" action="/ressources/<?= (int) $r['id'] ?>/delete" onsubmit="return confirm('Supprimer cette ressource ?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn ghost sm danger"><?= icon('trash', '', 13) ?> Supprimer</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: /* onglet sessions */ ?>

    <?php if ($sessions === []): ?>
        <div class="session-empty">
            <p>Aucune session pour le moment.</p>
            <a href="/sessions/create" class="btn primary" style="margin-top:8px">
                <?= icon('plus', '', 14) ?> Créer une session
            </a>
        </div>
    <?php else: ?>
        <div class="session-table-wrap">
            <table class="session-table">
                <thead>
                    <tr>
                        <th>Libellé</th>
                        <th>Ressource</th>
                        <th>Type</th>
                        <th>État</th>
                        <th>Code</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $s): ?>
                    <tr>
                        <td style="font-weight:600;color:var(--gray-800)"><?= htmlspecialchars((string) $s['name']) ?></td>
                        <td><span style="font-family:var(--font-mono);font-size:13px"><?= htmlspecialchars((string) ($s['resourceName'] ?? '—')) ?></span></td>
                        <td><span style="font-size:13px;color:var(--gray-500)"><?= htmlspecialchars((string) ($s['typeLabel'] ?? '—')) ?></span></td>
                        <td><span class="badge <?= htmlspecialchars($s['statusClass'] ?? 'badge-draft') ?>"><?= htmlspecialchars($s['statusLabel'] ?? 'Inconnu') ?></span></td>
                        <td>
                            <?php if (!empty($s['accessCode'])): ?>
                                <span style="font-family:var(--font-mono);font-size:13px;letter-spacing:.05em"><?= htmlspecialchars((string) $s['accessCode']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--gray-400)">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="cell-actions">
                            <a href="/sessions/<?= (int) $s['id'] ?>" class="btn ghost sm"><?= icon('eye', '', 13) ?> Voir</a>
                            <a href="/sessions/<?= (int) $s['id'] ?>/edit" class="btn ghost sm"><?= icon('edit', '', 13) ?> Modifier</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php endif; ?>

</div>