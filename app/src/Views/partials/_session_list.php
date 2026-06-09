<?php
/**
 * Sessions list — desktop table + mobile cards. Shared by the owned list and
 * the read-only "supervised" list on /sessions.
 *
 * @var list<array<string, mixed>> $rows      Presentation rows (SessionService::listRow)
 * @var bool                       $readonly  true => no mutating actions, a "lecture seule"
 *                                            badge and a Suivi link (supervised sessions)
 * @var callable(string):string    $typeIcon  type CSS class -> Lucide icon name (set by the view)
 */
$rows = $rows ?? [];
$readonly = $readonly ?? false;
?>
<!-- Desktop table -->
<div class="session-table-wrap">
    <table class="session-table">
        <thead>
            <tr>
                <th>Session</th>
                <th>Statut</th>
                <th>Code</th>
                <th>Créneau</th>
                <th class="cell-actions"><span style="position:absolute;width:1px;height:1px;clip:rect(0 0 0 0);overflow:hidden;">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $s): ?>
                <tr>
                    <td>
                        <a href="/sessions/<?= (int) $s['id'] ?>" class="session-cell-main">
                            <span class="stype-ico <?= htmlspecialchars($s['typeClass']) ?>"><?= icon($typeIcon($s['typeClass']), '', 17) ?></span>
                            <span class="session-cell-txt">
                                <b><?= htmlspecialchars($s['name']) ?></b>
                                <span><?= htmlspecialchars($s['typeLabel']) ?><?= $readonly ? ' · lecture seule' : '' ?></span>
                            </span>
                        </a>
                    </td>
                    <td><span class="badge badge-dot <?= htmlspecialchars($s['statusClass']) ?>"><?= htmlspecialchars($s['statusLabel']) ?></span></td>
                    <td><code class="access-code-cell"><?= htmlspecialchars($s['accessCode'] !== '' ? $s['accessCode'] : '—') ?></code></td>
                    <td>
                        <span class="session-slot">
                            <?= icon('clock', '', 14) ?>
                            <?= htmlspecialchars($s['startsAtFormatted'] ?? '—') ?> <span class="arr">→</span> <?= htmlspecialchars($s['endsAtFormatted'] ?? '—') ?>
                        </span>
                    </td>
                    <td class="cell-actions">
                        <a href="/sessions/<?= (int) $s['id'] ?>" class="icon-btn" title="Voir">
                            <?= icon('eye') ?>
                        </a>
                        <?php if ($readonly): ?>
                            <a href="/sessions/<?= (int) $s['id'] ?>/monitor" class="icon-btn" title="Suivi">
                                <?= icon('user') ?>
                            </a>
                        <?php else: ?>
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
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Mobile cards -->
<div class="session-cards">
    <?php foreach ($rows as $s): ?>
        <article class="session-card">
            <div class="session-card-head">
                <div>
                    <a href="/sessions/<?= (int) $s['id'] ?>" class="session-card-title" style="text-decoration:none;">
                        <?= htmlspecialchars($s['name']) ?>
                    </a>
                    <div class="session-card-meta">
                        <span class="badge <?= htmlspecialchars($s['typeClass']) ?>"><?= htmlspecialchars($s['typeLabel']) ?></span>
                        <span class="badge <?= htmlspecialchars($s['statusClass']) ?>"><?= htmlspecialchars($s['statusLabel']) ?></span>
                        <?php if ($readonly): ?>
                            <span class="badge">lecture seule</span>
                        <?php endif; ?>
                    </div>
                </div>
                <code class="access-code-cell"><?= htmlspecialchars($s['accessCode'] !== '' ? $s['accessCode'] : '—') ?></code>
            </div>
            <div class="session-card-actions">
                <a href="/sessions/<?= (int) $s['id'] ?>" class="btn sm">Dashboard</a>
                <?php if ($readonly): ?>
                    <a href="/sessions/<?= (int) $s['id'] ?>/monitor" class="btn sm">Suivi</a>
                <?php else: ?>
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
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
