<?php
/**
 * @var \App\Application\DTOs\SessionListView[] $sessions
 */
?>
<div class="page-header">
    <div class="page-header-row">
        <h1>Mes sessions</h1>
        <span class="mono" style="font-size:11px;color:var(--gray-400);"><?= count($sessions) ?> session(s)</span>
    </div>
    <p class="page-sub">Créez une session de cours ou d'examen, puis donnez le code d'accès à vos étudiants.</p>
</div>

<div class="page-body">
<div class="session-toolbar">
    <span class="grow"></span>
    <a href="/sessions/create" class="btn primary">
        <?= icon('graduation-cap', '', 14) ?> Nouvelle session
    </a>
</div>

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
                    <th>Libellé</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Code</th>
                    <th>Démarrage</th>
                    <th>Fin</th>
                    <th class="cell-actions"><span style="position:absolute;width:1px;height:1px;clip:rect(0 0 0 0);overflow:hidden;">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $s): ?>
                    <tr>
                        <td>
                            <a href="/sessions/<?= (int) $s->id ?>" style="text-decoration:none;font-weight:500;">
                                <?= htmlspecialchars($s->name) ?>
                            </a>
                        </td>
                        <td><span class="badge <?= htmlspecialchars($s->typeClass) ?>"><?= htmlspecialchars($s->typeLabel) ?></span></td>
                        <td><span class="badge <?= htmlspecialchars($s->statusClass) ?>"><?= htmlspecialchars($s->statusLabel) ?></span></td>
                        <td><code class="access-code-cell"><?= htmlspecialchars($s->accessCode) ?></code></td>
                        <td><?= htmlspecialchars($s->startsAtFormatted ?? '—') ?></td>
                        <td><?= htmlspecialchars($s->endsAtFormatted ?? '—') ?></td>
                        <td class="cell-actions">
                            <a href="/sessions/<?= (int) $s->id ?>" class="icon-btn" title="Voir">
                                <?= icon('eye') ?>
                            </a>
                            <?php if ($s->canEdit): ?>
                                <a href="/sessions/<?= (int) $s->id ?>/edit" class="icon-btn" title="Modifier">
                                    <?= icon('edit') ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($s->canStart): ?>
                                <form method="POST" action="/sessions/<?= (int) $s->id ?>/start">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="icon-btn success" title="Démarrer">
                                        <?= icon('play') ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($s->canEnd): ?>
                                <form method="POST" action="/sessions/<?= (int) $s->id ?>/end"
                                      onsubmit="return confirm('Terminer cette session maintenant ?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="icon-btn warning" title="Terminer">
                                        <?= icon('square') ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($s->canCancel): ?>
                                <form method="POST" action="/sessions/<?= (int) $s->id ?>/cancel"
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
                        <a href="/sessions/<?= (int) $s->id ?>" class="session-card-title" style="text-decoration:none;">
                            <?= htmlspecialchars($s->name) ?>
                        </a>
                        <div class="session-card-meta">
                            <span class="badge <?= htmlspecialchars($s->typeClass) ?>"><?= htmlspecialchars($s->typeLabel) ?></span>
                            <span class="badge <?= htmlspecialchars($s->statusClass) ?>"><?= htmlspecialchars($s->statusLabel) ?></span>
                        </div>
                    </div>
                    <code class="access-code-cell"><?= htmlspecialchars($s->accessCode) ?></code>
                </div>
                <div class="session-card-actions">
                    <a href="/sessions/<?= (int) $s->id ?>" class="btn sm">Dashboard</a>
                    <?php if ($s->canEdit): ?>
                        <a href="/sessions/<?= (int) $s->id ?>/edit" class="btn sm">Modifier</a>
                    <?php endif; ?>
                    <?php if ($s->canStart): ?>
                        <form method="POST" action="/sessions/<?= (int) $s->id ?>/start">
                            <?= csrf_field() ?>
                            <button class="btn sm success">Démarrer</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($s->canEnd): ?>
                        <form method="POST" action="/sessions/<?= (int) $s->id ?>/end">
                            <?= csrf_field() ?>
                            <button class="btn sm">Terminer</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div><!-- /.page-body -->
