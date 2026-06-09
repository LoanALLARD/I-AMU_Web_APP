<?php
/**
 * Read-only session supervision (teacher).
 *
 * @var array $view  monitor data:
 *   id, name, accessCode, statusLabel, statusClass, studentCount,
 *   students[] (id, name, totalPrompts, conversations[] (id, name, promptCount, lastActivity, lastModel)),
 *   selected|null (conversationId, conversationName, studentName, transcript[] (prompt, response, model, sentAt))
 */
$activeConvId = $view['selected']['conversationId'] ?? null;
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

        <!-- Left : students, each with their conversations -->
        <aside class="monitor-list">
            <?php if ($view['students'] === []): ?>
                <p class="conv-empty">Aucun étudiant n'a rejoint cette session.</p>
            <?php else: ?>
                <?php foreach ($view['students'] as $stu): ?>
                    <?php
                    // Collapsed by default; open the group that holds the
                    // currently selected conversation.
                    $hasActive = false;
                    foreach ($stu['conversations'] as $cc) {
                        if ((int) $cc['id'] === (int) $activeConvId) {
                            $hasActive = true;
                            break;
                        }
                    }
                    ?>
                    <details class="monitor-student-group"<?= $hasActive ? ' open' : '' ?>>
                        <summary class="monitor-student-head">
                            <svg class="monitor-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                            <span class="monitor-student-name"><?= htmlspecialchars($stu['name']) ?></span>
                            <span class="monitor-student-conv-count"><?= count($stu['conversations']) ?> conv.</span>
                            <span class="monitor-student-count" title="total prompts"><?= (int) $stu['totalPrompts'] ?></span>
                        </summary>
                        <?php if ($stu['conversations'] === []): ?>
                            <p class="monitor-muted monitor-noconv">aucune conversation</p>
                        <?php else: ?>
                            <?php foreach ($stu['conversations'] as $conv): ?>
                                <a href="/sessions/<?= (int) $view['id'] ?>/monitor?conversation=<?= (int) $conv['id'] ?>"
                                    class="monitor-conv<?= (int) $conv['id'] === (int) $activeConvId ? ' is-active' : '' ?>">
                                    <span class="monitor-conv-name"><?= htmlspecialchars($conv['name']) ?></span>
                                    <span class="monitor-conv-meta">
                                        <span class="monitor-conv-count"><?= (int) $conv['promptCount'] ?> prompt(s)</span>
                                        <?php if ($conv['lastActivity'] !== null): ?>
                                            <span class="monitor-muted">· <?= htmlspecialchars($conv['lastActivity']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </aside>

        <!-- Right : selected conversation transcript -->
        <section class="monitor-transcript">
            <?php if ($view['selected'] === null): ?>
                <div class="monitor-empty">
                    <?= icon('message-circle', '', 40) ?>
                    <p>Sélectionnez une conversation pour voir l'historique des prompts.</p>
                </div>
            <?php else: ?>
                <div class="monitor-transcript-head">
                    <strong><?= htmlspecialchars($view['selected']['studentName']) ?></strong>
                    <span class="monitor-muted"><?= htmlspecialchars($view['selected']['conversationName']) ?></span>
                    <span class="monitor-muted">· <?= count($view['selected']['transcript']) ?> prompt(s)</span>
                </div>
                <?php if ($view['selected']['transcript'] === []): ?>
                    <div class="monitor-empty">
                        <p>Aucun prompt enregistré dans cette conversation.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($view['selected']['transcript'] as $idx => $t): ?>
                        <div class="transcript-turn">
                            <div class="transcript-meta">
                                <span class="transcript-num"><?= str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT) ?></span>
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
