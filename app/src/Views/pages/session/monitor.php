<?php
/**
 * Read-only session supervision (teacher).
 *
 * @var array $view  monitor data:
 *   id, name, accessCode, statusLabel, statusClass, studentCount,
 *   students[] (id, name, promptCount, lastActivity, lastModel),
 *   selected|null (id, name, transcript[] (prompt, response, model, sentAt))
 */
?>
<div class="page-header">
    <div class="page-header-row" style="align-items:center;">
        <div>
            <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;">
                <h1>Suivi — <?= htmlspecialchars($view['name']) ?></h1>
                <span class="badge <?= htmlspecialchars($view['statusClass']) ?>"><?= htmlspecialchars($view['statusLabel']) ?></span>
            </div>
            <p class="page-sub" style="margin-top:6px;">
                <?= (int) $view['studentCount'] ?> étudiant(s) lié(s)<?= $view['accessCode'] !== '' ? ' · session ' . htmlspecialchars($view['accessCode']) : '' ?>
            </p>
        </div>
        <a href="/sessions/<?= (int) $view['id'] ?>" class="btn" style="margin-left:auto;">
            <?= icon('book', '', 14) ?> Dashboard
        </a>
    </div>
</div>

<div class="page-body">
    <div class="monitor-grid">

        <!-- Left : enrolled students -->
        <aside class="monitor-list">
            <?php if ($view['students'] === []): ?>
                <p class="conv-empty">Aucun étudiant n'a rejoint cette session.</p>
            <?php else: ?>
                <?php foreach ($view['students'] as $s): ?>
                    <a href="/sessions/<?= (int) $view['id'] ?>/monitor?student=<?= (int) $s['id'] ?>"
                        class="monitor-student<?= (($view['selected']['id'] ?? null) === $s['id']) ? ' is-active' : '' ?>">
                        <div class="monitor-student-top">
                            <span class="monitor-student-name"><?= htmlspecialchars($s['name']) ?></span>
                            <span class="monitor-student-count" title="prompts"><?= (int) $s['promptCount'] ?></span>
                        </div>
                        <div class="monitor-student-meta">
                            <?php if ($s['lastModel'] !== null): ?>
                                <span class="mono"><?= htmlspecialchars($s['lastModel']) ?></span>
                            <?php endif; ?>
                            <?php if ($s['lastActivity'] !== null): ?>
                                <span><?= $s['lastModel'] !== null ? '· ' : '' ?><?= htmlspecialchars($s['lastActivity']) ?></span>
                            <?php else: ?>
                                <span class="monitor-muted">aucune activité</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </aside>

        <!-- Right : selected student's transcript -->
        <section class="monitor-transcript">
            <?php if ($view['selected'] === null): ?>
                <div class="monitor-empty">
                    <?= icon('message-circle', '', 40) ?>
                    <p>Sélectionnez un étudiant pour voir l'historique de ses prompts.</p>
                </div>
            <?php else: ?>
                <div class="monitor-transcript-head">
                    <strong><?= htmlspecialchars($view['selected']['name']) ?></strong>
                    <span class="monitor-muted"><?= count($view['selected']['transcript']) ?> prompt(s)</span>
                </div>
                <?php if ($view['selected']['transcript'] === []): ?>
                    <div class="monitor-empty">
                        <p>Aucun prompt enregistré pour cet étudiant.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($view['selected']['transcript'] as $i => $t): ?>
                        <div class="transcript-turn">
                            <div class="transcript-meta">
                                <span class="transcript-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                <span class="mono"><?= htmlspecialchars($t['model']) ?></span>
                                <span>· <?= htmlspecialchars($t['sentAt']) ?></span>
                            </div>
                            <div class="transcript-prompt"><?= nl2br(htmlspecialchars($t['prompt'])) ?></div>
                            <?php if ($t['response'] !== ''): ?>
                                <div class="transcript-response"><?= nl2br(htmlspecialchars($t['response'])) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </section>

    </div>
</div>
