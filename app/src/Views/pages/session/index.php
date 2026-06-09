<?php
/**
 * @var list<array<string, mixed>> $sessions  Presentation rows from SessionService::listForTeacher()
 */
// Type badge class → Lucide icon shown in the tinted square of each row.
$typeIcon = static fn(string $typeClass): string => [
    'badge-exam' => 'lock',
    'badge-course' => 'book',
][$typeClass] ?? 'book';
?>
<div class="page-header">
    <div class="page-header-row" style="align-items:center;">
        <div>
            <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;">
                <h1>Mes sessions</h1>
                <span class="mono" style="font-size:11px;color:var(--gray-400);"><?= count($sessions) ?>
                    session(s)</span>
            </div>
            <p class="page-sub" style="margin-top:6px;">Créez une session de cours ou d'examen, puis donnez le code
                d'accès à vos étudiants.</p>
        </div>
        <a href="/sessions/create" class="btn primary" style="margin-left:auto;">
            <?= icon('graduation-cap', '', 14) ?> Nouvelle session
        </a>
    </div>
</div>

<div class="page-body">

    <?php if ($sessions === []): ?>
        <div class="session-empty">
            <p>Aucune session pour le moment.</p>
            <a href="/sessions/create" class="btn bordered">Créer ma première session</a>
        </div>
    <?php else: ?>
        <!-- Desktop table -->
        <div class="session-table-wrap">
            <table class="session-table">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>Statut</th>
                        <th>Code</th>
                        <th>Créneau</th>
                        <th class="cell-actions"><span
                                style="position:absolute;width:1px;height:1px;clip:rect(0 0 0 0);overflow:hidden;">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td>
                                <a href="/sessions/<?= (int) $s['id'] ?>" class="session-cell-main">
                                    <span
                                        class="stype-ico <?= htmlspecialchars($s['typeClass']) ?>"><?= icon($typeIcon($s['typeClass']), '', 17) ?></span>
                                    <span class="session-cell-txt">
                                        <b><?= htmlspecialchars($s['name']) ?></b>
                                        <span><?= htmlspecialchars($s['typeLabel']) ?></span>
                                    </span>
                                </a>
                            </td>
                            <td><span
                                    class="badge badge-dot <?= htmlspecialchars($s['statusClass']) ?>"><?= htmlspecialchars($s['statusLabel']) ?></span>
                            </td>
                            <td><code
                                    class="access-code-cell"><?= htmlspecialchars($s['accessCode'] !== '' ? $s['accessCode'] : '—') ?></code>
                            </td>
                            <td>
                                <span class="session-slot">
                                    <?= icon('clock', '', 14) ?>
                                    <?= htmlspecialchars($s['startsAtFormatted'] ?? '—') ?> <span class="arr">→</span>
                                    <?= htmlspecialchars($s['endsAtFormatted'] ?? '—') ?>
                                </span>
                            </td>
                            <td class="cell-actions">
                                <a href="/sessions/<?= (int) $s['id'] ?>" class="icon-btn" title="Voir">
                                    <?= icon('eye') ?>
                                </a>
                                <?php if ($s['canEdit']): ?>
                                    <a href="/sessions/<?= (int) $s['id'] ?>/edit" class="icon-btn" title="Modifier">
                                        <?= icon('edit') ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($s['canStart']): ?>
                                    <form method="POST" action="/sessions/<?= (int) $s['id'] ?>/start">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn success" title="Démarrer">
                                            <?= icon('play') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($s['canEnd']): ?>
                                    <form method="POST" action="/sessions/<?= (int) $s['id'] ?>/end"
                                        onsubmit="return confirm('Terminer cette session maintenant ?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn warning" title="Terminer">
                                            <?= icon('square') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($s['canCancel']): ?>
                                    <form method="POST" action="/sessions/<?= (int) $s['id'] ?>/cancel"
                                        onsubmit="return confirm('Annuler cette session ?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="icon-btn danger" title="Annuler">
                                            <?= icon('x-circle') ?>
                                        </button>
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
            <?php foreach ($sessions as $s): ?>
                <article class="session-card">
                    <div class="session-card-head">
                        <div>
                            <a href="/sessions/<?= (int) $s['id'] ?>" class="session-card-title" style="text-decoration:none;">
                                <?= htmlspecialchars($s['name']) ?>
                            </a>
                            <div class="session-card-meta">
                                <span
                                    class="badge <?= htmlspecialchars($s['typeClass']) ?>"><?= htmlspecialchars($s['typeLabel']) ?></span>
                                <span
                                    class="badge <?= htmlspecialchars($s['statusClass']) ?>"><?= htmlspecialchars($s['statusLabel']) ?></span>
                            </div>
                        </div>
                        <code
                            class="access-code-cell"><?= htmlspecialchars($s['accessCode'] !== '' ? $s['accessCode'] : '—') ?></code>
                    </div>
                    <div class="session-card-actions">
                        <a href="/sessions/<?= (int) $s['id'] ?>" class="btn sm">Dashboard</a>
                        <?php if ($s['canEdit']): ?>
                            <a href="/sessions/<?= (int) $s['id'] ?>/edit" class="btn sm">Modifier</a>
                        <?php endif; ?>
                        <?php if ($s['canStart']): ?>
                            <form method="POST" action="/sessions/<?= (int) $s['id'] ?>/start">
                                <?= csrf_field() ?>
                                <button class="btn sm success">Démarrer</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($s['canEnd']): ?>
                            <form method="POST" action="/sessions/<?= (int) $s['id'] ?>/end">
                                <?= csrf_field() ?>
                                <button class="btn sm">Terminer</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php $supervised = $supervised ?? []; ?>
    <?php if ($supervised !== []): ?>
        <div class="page-header" style="margin-top:28px;">
            <div class="page-header-row" style="align-items:center;">
                <div>
                    <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;">
                        <h1>Sessions surveillées</h1>
                        <span class="mono" style="font-size:11px;color:var(--gray-400);"><?= count($supervised) ?>
                            session(s)</span>
                    </div>
                    <p class="page-sub" style="margin-top:6px;">Sessions de ressources où vous êtes enseignant responsable —
                        lecture seule.</p>
                </div>
            </div>
        </div>

        <!-- Desktop table -->
        <div class="session-table-wrap">
            <table class="session-table">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>Statut</th>
                        <th>Code</th>
                        <th>Créneau</th>
                        <th class="cell-actions"><span
                                style="position:absolute;width:1px;height:1px;clip:rect(0 0 0 0);overflow:hidden;">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($supervised as $s): ?>
                        <tr>
                            <td>
                                <a href="/sessions/<?= (int) $s['id'] ?>" class="session-cell-main">
                                    <span
                                        class="stype-ico <?= htmlspecialchars($s['typeClass']) ?>"><?= icon($typeIcon($s['typeClass']), '', 17) ?></span>
                                    <span class="session-cell-txt">
                                        <b><?= htmlspecialchars($s['name']) ?></b>
                                        <span><?= htmlspecialchars($s['typeLabel']) ?> · lecture seule</span>
                                    </span>
                                </a>
                            </td>
                            <td><span
                                    class="badge badge-dot <?= htmlspecialchars($s['statusClass']) ?>"><?= htmlspecialchars($s['statusLabel']) ?></span>
                            </td>
                            <td><code
                                    class="access-code-cell"><?= htmlspecialchars($s['accessCode'] !== '' ? $s['accessCode'] : '—') ?></code>
                            </td>
                            <td>
                                <span class="session-slot">
                                    <?= icon('clock', '', 14) ?>
                                    <?= htmlspecialchars($s['startsAtFormatted'] ?? '—') ?> <span class="arr">→</span>
                                    <?= htmlspecialchars($s['endsAtFormatted'] ?? '—') ?>
                                </span>
                            </td>
                            <td class="cell-actions">
                                <a href="/sessions/<?= (int) $s['id'] ?>" class="icon-btn" title="Voir">
                                    <?= icon('eye') ?>
                                </a>
                                <a href="/sessions/<?= (int) $s['id'] ?>/monitor" class="icon-btn" title="Suivi">
                                    <?= icon('user') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="session-cards">
            <?php foreach ($supervised as $s): ?>
                <article class="session-card">
                    <div class="session-card-head">
                        <div>
                            <a href="/sessions/<?= (int) $s['id'] ?>" class="session-card-title" style="text-decoration:none;">
                                <?= htmlspecialchars($s['name']) ?>
                            </a>
                            <div class="session-card-meta">
                                <span
                                    class="badge <?= htmlspecialchars($s['typeClass']) ?>"><?= htmlspecialchars($s['typeLabel']) ?></span>
                                <span
                                    class="badge <?= htmlspecialchars($s['statusClass']) ?>"><?= htmlspecialchars($s['statusLabel']) ?></span>
                                <span class="badge">lecture seule</span>
                            </div>
                        </div>
                        <code
                            class="access-code-cell"><?= htmlspecialchars($s['accessCode'] !== '' ? $s['accessCode'] : '—') ?></code>
                    </div>
                    <div class="session-card-actions">
                        <a href="/sessions/<?= (int) $s['id'] ?>" class="btn sm">Dashboard</a>
                        <a href="/sessions/<?= (int) $s['id'] ?>/monitor" class="btn sm">Suivi</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div><!-- /.page-body -->
<?php /* Click-to-copy on the .access-code-cell chips is wired globally by
/assets/js/clipboard.js (loaded in the layout). */ ?>