<?php
/**
 * Teacher's resource + session tabbed list.
 *
 * @var list<array<string, mixed>> $ressources
 * @var list<array<string, mixed>> $sessions     Owned sessions (SessionService::listForTeacher)
 * @var list<array<string, mixed>> $supervised   Read-only supervised sessions
 * @var string                     $navSection   'ressources' | 'sessions'
 * @var array<string, mixed>       $user
 */

$activeTab  = $navSection === 'sessions' ? 'sessions' : 'ressources';
$supervised = $supervised ?? [];
// Type badge class -> Lucide icon, used by the shared _session_list partial.
$typeIcon = static fn (string $typeClass): string => [
    'badge-exam'   => 'lock',
    'badge-course' => 'book',
][$typeClass] ?? 'book';

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
            <a href="/ressources/create" class="btn primary page-header-cta">
                <?= icon('plus', '', 14) ?> Nouvelle ressource
            </a>
        <?php else: ?>
            <h1 class="page-title"><?= icon('graduation-cap', '', 18) ?> Mes sessions</h1>
            <a href="/sessions/create" class="btn primary page-header-cta">
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
                        <td><span class="res-code"><?= htmlspecialchars((string) $r['code']) ?></span></td>
                        <td>
                            <div class="res-name"><?= htmlspecialchars((string) $r['name']) ?></div>
                            <?php if (!empty($r['description'])): ?>
                                <div class="res-desc"><?= htmlspecialchars(mb_strimwidth((string) $r['description'], 0, 72, '…')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($r['semester'])): ?>
                                <span class="cell-mono"><?= htmlspecialchars((string) $r['semester']) ?></span>
                            <?php else: ?>
                                <span class="cell-muted">—</span>
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
                    <span class="cell-mono cell-muted" style="font-size:12px"><?= htmlspecialchars((string) $r['code']) ?></span>
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

    <?php if ($sessions === [] && $supervised === []): ?>
        <div class="session-empty">
            <p>Aucune session pour le moment.</p>
            <a href="/sessions/create" class="btn primary" style="margin-top:8px">
                <?= icon('plus', '', 14) ?> Créer une session
            </a>
        </div>
    <?php else: ?>
        <?php /* Shared session list (desktop table + mobile cards), reused for
           the owned list and the read-only supervised list. */ ?>
        <?php if ($sessions !== []): ?>
            <?php $rows = $sessions; $readonly = false; require __DIR__ . '/../../partials/_session_list.php'; ?>
        <?php endif; ?>

        <?php if ($supervised !== []): ?>
            <div class="page-header" style="margin-top:28px;">
                <div class="page-header-row" style="align-items:center;">
                    <div>
                        <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;">
                            <h2 style="margin:0;font-size:18px;">Sessions surveillées</h2>
                            <span class="mono" style="font-size:11px;color:var(--gray-400);"><?= count($supervised) ?> session(s)</span>
                        </div>
                        <p class="page-sub" style="margin-top:6px;">Sessions de ressources où vous êtes enseignant responsable — lecture seule.</p>
                    </div>
                </div>
            </div>
            <?php $rows = $supervised; $readonly = true; require __DIR__ . '/../../partials/_session_list.php'; ?>
        <?php endif; ?>
    <?php endif; ?>

<?php endif; ?>

</div>