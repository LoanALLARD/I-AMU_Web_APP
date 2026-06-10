<?php
/**
 * Session statistics page (teacher, running or ended session).
 *
 * @var array<string, mixed> $stats  SessionService::statistics()
 */
$k        = $stats['kpi'];
$students = $stats['students'];
$activity = $stats['activity'];
$fb       = $stats['feedback'];
$fmt      = static fn (int $n): string => number_format($n, 0, ',', ' ');
?>
<style>
    .stat-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px; margin-bottom:20px; }
    .stat-card { background:var(--white); border:1px solid var(--gray-200); border-radius:var(--radius); padding:14px 16px; }
    .stat-card .stat-val { font-size:26px; font-weight:700; letter-spacing:-.02em; }
    .stat-card .stat-key { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--gray-500); margin-top:2px; }
    .stat-card .stat-sub { font-size:12px; color:var(--gray-400); margin-top:4px; }
    .bars { display:flex; align-items:flex-end; gap:6px; min-height:120px; padding-top:8px; }
    .bars .bar { flex:1; min-width:14px; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; gap:4px; height:120px; }
    .bars .bar-fill { width:100%; background:var(--blue); border-radius:4px 4px 0 0; min-height:3px; }
    .bars .bar-n { font-size:11px; color:var(--gray-600); }
    .bars .bar-x { font-size:10px; color:var(--gray-400); white-space:nowrap; }
    .fb-bar { display:flex; height:14px; border-radius:7px; overflow:hidden; background:var(--gray-200); margin:10px 0 8px; }
    .fb-bar > span { display:block; height:100%; }
    .tok-bar { height:6px; border-radius:3px; background:var(--gray-200); margin-top:4px; overflow:hidden; max-width:120px; }
    .tok-bar > span { display:block; height:100%; background:var(--blue); }
    .stat-muted { color:var(--gray-400); }
</style>

<div class="dashboard-header">
    <div>
        <h1 style="margin:0;font-size:24px;font-weight:600;letter-spacing:-0.02em;">
            Statistiques — <?= htmlspecialchars($stats['name']) ?>
        </h1>
        <div class="dashboard-meta">
            <span class="badge <?= htmlspecialchars($stats['statusClass']) ?>"><?= htmlspecialchars($stats['statusLabel']) ?></span>
            <?php if (!empty($stats['isOngoing'])): ?>
                <span class="stat-muted" style="font-size:13px;">· session en cours — données partielles</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="dashboard-actions">
        <a href="/sessions/<?= (int) $stats['id'] ?>" class="btn">Retour au tableau de bord</a>
    </div>
</div>

<div class="page-body">
    <!-- KPI -->
    <div class="stat-cards">
        <div class="stat-card">
            <div class="stat-val"><?= (int) $k['active'] ?>/<?= (int) $k['enrolled'] ?></div>
            <div class="stat-key">Participants</div>
            <div class="stat-sub"><?= (int) $k['participationRate'] ?> % des inscrits</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?= $fmt((int) $k['prompts']) ?></div>
            <div class="stat-key">Prompts</div>
            <div class="stat-sub"><?= $k['active'] > 0 ? $fmt((int) round($k['prompts'] / $k['active'])) : 0 ?> / participant</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?= $fmt((int) $k['totalTokens']) ?></div>
            <div class="stat-key">Tokens</div>
            <div class="stat-sub"><?= $fmt((int) $k['inputTokens']) ?> in · <?= $fmt((int) $k['outputTokens']) ?> out</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?= $fmt((int) $k['avgLatency']) ?> ms</div>
            <div class="stat-key">Latence moy.</div>
            <div class="stat-sub">médiane <?= $fmt((int) $k['medianLatency']) ?> ms</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?= $fmt((int) $k['avgPromptLen']) ?> / <?= $fmt((int) $k['avgResponseLen']) ?></div>
            <div class="stat-key">Longueur moy.</div>
            <div class="stat-sub">prompt / réponse (car.)</div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div>
            <!-- Par étudiant -->
            <div class="dashboard-card">
                <h2>Par étudiant</h2>
                <?php if ($students === []): ?>
                    <p class="stat-muted" style="font-size:13px;">Aucun étudiant inscrit.</p>
                <?php else: ?>
                    <?php if ($stats['inactiveCount'] > 0): ?>
                        <p class="stat-muted" style="font-size:12px;margin:0 0 10px;">
                            <?= (int) $stats['inactiveCount'] ?> étudiant(s) inscrit(s) sans aucune activité.
                        </p>
                    <?php endif; ?>
                    <div class="session-table-wrap">
                        <table class="session-table">
                            <thead>
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Conv.</th>
                                    <th>Prompts</th>
                                    <th>Tokens<?= $stats['maxTokens'] !== null ? ' (/ plafond)' : '' ?></th>
                                    <th>Dernière activité</th>
                                    <th>Avis</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $st): ?>
                                    <tr<?= $st['active'] ? '' : ' style="opacity:.55;"' ?>>
                                        <td>
                                            <b><?= htmlspecialchars($st['name']) ?></b>
                                            <?php if ($st['studentNumber']): ?>
                                                <br><span class="stat-muted" style="font-size:11px;"><?= htmlspecialchars($st['studentNumber']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= (int) $st['conversations'] ?></td>
                                        <td><?= (int) $st['prompts'] ?></td>
                                        <td>
                                            <?= $fmt((int) $st['tokens']) ?>
                                            <?php if ($st['tokensPct'] !== null): ?>
                                                <div class="tok-bar"><span style="width:<?= (int) $st['tokensPct'] ?>%;"></span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $st['lastActivity'] !== null ? htmlspecialchars($st['lastActivity']) : '<span class="stat-muted">jamais</span>' ?></td>
                                        <td>
                                            <?php if ($st['feedbackUp'] || $st['feedbackDown']): ?>
                                                <span style="color:var(--accept);">▲ <?= (int) $st['feedbackUp'] ?></span>
                                                <span style="color:var(--refuse);margin-left:6px;">▼ <?= (int) $st['feedbackDown'] ?></span>
                                            <?php else: ?>
                                                <span class="stat-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Activité dans le temps -->
            <div class="dashboard-card">
                <h2>Activité dans le temps</h2>
                <?php if ($activity === []): ?>
                    <p class="stat-muted" style="font-size:13px;">Aucune activité.</p>
                <?php else: ?>
                    <div class="bars">
                        <?php foreach ($activity as $a): ?>
                            <div class="bar" title="<?= (int) $a['prompts'] ?> prompt(s)">
                                <span class="bar-n"><?= (int) $a['prompts'] ?></span>
                                <span class="bar-fill" style="height:<?= (int) $a['heightPct'] ?>%;"></span>
                                <span class="bar-x"><?= htmlspecialchars($a['label']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <!-- Satisfaction -->
            <div class="dashboard-card">
                <h2>Satisfaction</h2>
                <?php if ($fb['rated'] === 0): ?>
                    <p class="stat-muted" style="font-size:13px;">Aucun avis donné par les étudiants.</p>
                <?php else: $tot = max(1, (int) $fb['rated']); ?>
                    <div class="fb-bar">
                        <span style="width:<?= round($fb['up'] / $tot * 100) ?>%;background:var(--accept);"></span>
                        <span style="width:<?= round($fb['neutral'] / $tot * 100) ?>%;background:var(--gray-400);"></span>
                        <span style="width:<?= round($fb['down'] / $tot * 100) ?>%;background:var(--refuse);"></span>
                    </div>
                    <div style="display:flex;gap:14px;font-size:13px;align-items:center;">
                        <span style="color:var(--accept);">▲ <?= (int) $fb['up'] ?></span>
                        <span class="stat-muted">● <?= (int) $fb['neutral'] ?></span>
                        <span style="color:var(--refuse);">▼ <?= (int) $fb['down'] ?></span>
                        <span class="stat-muted" style="margin-left:auto;"><?= (int) $fb['rated'] ?> avis</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Modèles -->
            <div class="dashboard-card">
                <h2>Modèles utilisés</h2>
                <?php if ($stats['byModel'] === []): ?>
                    <p class="stat-muted" style="font-size:13px;">—</p>
                <?php else: ?>
                    <div class="kv-grid">
                        <?php foreach ($stats['byModel'] as $m): ?>
                            <span class="kv-key"><?= htmlspecialchars($m['name']) ?></span>
                            <span class="kv-val mono"><?= (int) $m['prompts'] ?> prompts · <?= $fmt((int) $m['tokens']) ?> tok</span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
