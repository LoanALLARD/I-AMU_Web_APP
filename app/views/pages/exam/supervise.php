<?php
// Filtre d'onglet courant (?tab=open|submitted|flagged|all)
$tab = $_GET['tab'] ?? 'open';

// Filtrage des étudiants selon l'onglet
$filtered = array_filter($students, function ($s) use ($tab) {
    if ($tab === 'all')       return true;
    if ($tab === 'submitted') return $s['submitted_at'] !== null;
    if ($tab === 'flagged')   return $s['flag_count'] > 0;
    return $s['submitted_at'] === null; // open
});

// Helpers temps relatif
$relTime = function ($ts) {
    if (!$ts) return '—';
    $diff = time() - strtotime($ts);
    if ($diff < 60)     return $diff . ' s';
    if ($diff < 3600)   return floor($diff / 60) . ' min';
    if ($diff < 86400)  return floor($diff / 3600) . ' h';
    return floor($diff / 86400) . ' j';
};

$startedRel = $session['starts_at'] ? $relTime($session['starts_at']) : '—';

// Comptes par onglet
$counts = [
    'open'      => count(array_filter($students, fn($s) => $s['submitted_at'] === null)),
    'submitted' => count(array_filter($students, fn($s) => $s['submitted_at'] !== null)),
    'flagged'   => $stats['flagged_count'],
    'all'       => count($students),
];
?>
<div class="supervise-page">

    <!-- ═══ HEADER ═══════════════════════════════════════════════ -->
    <header class="supervise-header">
        <div class="supervise-title">
            <h1><?= htmlspecialchars($session['name']) ?></h1>
            <div class="supervise-subtitle">
                <?= $stats['students_count'] ?> étudiant<?= $stats['students_count'] > 1 ? 's' : '' ?>
                · session <code class="access-code-inline"><?= htmlspecialchars($session['access_code']) ?></code>
                · démarrée il y a <?= $startedRel ?>
            </div>
        </div>
        <div class="supervise-actions">
            <form method="POST" action="/exam/<?= (int) $session['session_id'] ?>/archive"
                  onsubmit="return confirm('Marquer toute la session comme rendue ?')" style="margin:0;">
                <button type="submit" class="btn btn-secondary btn-sm">
                    Archiver toute la session
                </button>
            </form>
            <button type="button" class="btn btn-secondary btn-sm" disabled title="Bientôt">
                Note pour la classe
            </button>
        </div>
    </header>

    <!-- ═══ LAYOUT 2 COLONNES ════════════════════════════════════ -->
    <div class="supervise-layout">

        <!-- ─── Colonne gauche : liste étudiants ─────────────── -->
        <section class="supervise-list">
            <div class="supervise-search">
                <input type="search" id="student-search" placeholder="Rechercher un étudiant, un mot-clé, un modèle…"
                       class="session-input">
                <button type="button" class="btn btn-secondary btn-sm" disabled>Filtres</button>
            </div>

            <nav class="supervise-tabs">
                <?php foreach ([
                    'open'      => 'Ouvertes',
                    'submitted' => 'Rendues',
                    'flagged'   => 'Signalées',
                    'all'       => 'Toutes',
                ] as $key => $label): ?>
                    <a href="?tab=<?= $key ?><?= $selectedConvId ? '&conversation=' . $selectedConvId : '' ?>"
                       class="supervise-tab <?= $tab === $key ? 'active' : '' ?>">
                        <?= $label ?>
                        <span class="supervise-tab-count"><?= $counts[$key] ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="supervise-table-head">
                <span>Étudiant · dernier prompt</span>
                <span>Modèle</span>
                <span>Activité</span>
                <span>N°</span>
            </div>

            <div class="supervise-table-body" id="student-list">
                <?php if (empty($filtered)): ?>
                    <p class="text-muted" style="padding:1rem;">Aucun étudiant dans cet onglet.</p>
                <?php else: ?>
                    <?php foreach ($filtered as $s):
                        $isSelected = (int) $s['conversation_id'] === $selectedConvId;
                        $isFlagged  = $s['flag_count'] > 0;
                    ?>
                        <a href="?tab=<?= $tab ?>&conversation=<?= $s['conversation_id'] ?>"
                           class="supervise-row <?= $isSelected ? 'active' : '' ?>"
                           data-name="<?= htmlspecialchars(strtolower($s['last_name'] . ' ' . $s['first_name'])) ?>"
                           data-model="<?= htmlspecialchars(strtolower($s['last_model'] ?? '')) ?>"
                           data-prompt="<?= htmlspecialchars(strtolower($s['last_prompt'] ?? '')) ?>">
                            <div class="supervise-row-main">
                                <div class="supervise-row-status">
                                    <?php if ($isFlagged): ?>
                                        <span class="status-flag" title="Signalé"><?= icon('alert-triangle', 'text-error') ?></span>
                                    <?php elseif ($s['submitted_at']): ?>
                                        <span class="status-dot status-done" title="Rendue"></span>
                                    <?php else: ?>
                                        <span class="status-dot" title="Ouverte"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="supervise-row-body">
                                    <div class="supervise-row-name">
                                        <?= htmlspecialchars($s['last_name']) ?>, <?= htmlspecialchars($s['first_name']) ?>
                                    </div>
                                    <div class="supervise-row-prompt">
                                        » <?= htmlspecialchars(mb_substr($s['last_prompt'] ?? '— pas encore de prompt —', 0, 80)) ?><?= mb_strlen($s['last_prompt'] ?? '') > 80 ? '…' : '' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="supervise-row-model"><?= htmlspecialchars($s['last_model'] ?? '—') ?></div>
                            <div class="supervise-row-activity"><?= $relTime($s['last_at']) ?></div>
                            <div class="supervise-row-count"><?= (int) $s['prompt_count'] ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- ─── Colonne droite : conversation détaillée ──────── -->
        <section class="supervise-detail">
            <?php if (!$selectedRow): ?>
                <div class="supervise-detail-empty">
                    Sélectionnez un étudiant à gauche pour voir sa conversation.
                </div>
            <?php else: ?>
                <header class="supervise-detail-head">
                    <div class="detail-head-left">
                        <div class="detail-avatar"><?= strtoupper(mb_substr($selectedRow['first_name'], 0, 1) . mb_substr($selectedRow['last_name'], 0, 1)) ?></div>
                        <div>
                            <div class="detail-name">
                                <?= htmlspecialchars($selectedRow['last_name']) ?>, <?= htmlspecialchars($selectedRow['first_name']) ?>
                            </div>
                            <div class="detail-meta">
                                <?= htmlspecialchars($selectedRow['student_number'] ?? '—') ?>
                                · <?= htmlspecialchars($selectedRow['last_model'] ?? '—') ?>
                                · <?= (int) $selectedRow['prompt_count'] ?> prompt<?= $selectedRow['prompt_count'] > 1 ? 's' : '' ?>
                            </div>
                        </div>
                    </div>
                    <div class="detail-head-actions">
                        <?php if ($selectedRow['flag_count'] > 0): ?>
                            <span class="detail-flag-badge">
                                <?= icon('alert-triangle', 'text-error') ?>
                                Signalée
                                <?php
                                    // Récupère la 1ʳᵉ raison non vide
                                    $reasons = array_filter(array_map(fn($i) => $i['teacher_flag_reason'] ?? '', $selectedInteractions));
                                    if (!empty($reasons)) echo ' · ' . htmlspecialchars(reset($reasons));
                                ?>
                            </span>
                        <?php endif; ?>
                        <button type="button" class="btn btn-secondary btn-sm" id="btn-toggle-diff">
                            Diff vue cours / vue libre
                        </button>
                    </div>
                </header>

                <div class="supervise-conversation" id="conversation-view">
                    <?php foreach ($selectedInteractions as $i => $it): ?>
                        <div class="conv-turn">
                            <div class="conv-turn-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?> · ÉLÈVE</div>
                            <div class="conv-bubble conv-bubble-user">
                                <?= nl2br(htmlspecialchars($it['prompt'])) ?>
                            </div>
                        </div>

                        <?php if ($it['response']): ?>
                            <div class="conv-turn">
                                <div class="conv-turn-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?> · <?= htmlspecialchars(strtoupper($it['model_name'])) ?></div>
                                <div class="conv-bubble conv-bubble-ai">
                                    <?= nl2br(htmlspecialchars($it['response'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($it['teacher_comment'])): ?>
                            <div class="conv-margin">
                                <div class="conv-margin-head">
                                    Commentaire de marge
                                    · <?= htmlspecialchars(($_SESSION['user_first_name'] ?? '') . ' ' . ($_SESSION['user_last_name'] ?? '')) ?>
                                    · <?= $relTime($it['sent_at']) ?>
                                </div>
                                <div class="conv-margin-body">
                                    <?= nl2br(htmlspecialchars($it['teacher_comment'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if (empty($selectedInteractions)): ?>
                        <p class="text-muted">Aucun prompt envoyé pour cette conversation.</p>
                    <?php endif; ?>
                </div>

                <!-- Form pour ajouter un commentaire / signaler -->
                <form class="supervise-comment-form" id="flag-form">
                    <input type="hidden" name="prompt_id" id="flag-prompt-id"
                           value="<?= !empty($selectedInteractions) ? (int) end($selectedInteractions)['prompt_id'] : '' ?>">
                    <label class="section-title">Ajouter un commentaire de marge</label>
                    <div class="comment-row">
                        <select name="reason" class="session-input" style="max-width:200px;">
                            <option value="">— Raison (optionnel) —</option>
                            <option value="contourne-consigne">Contourne consigne</option>
                            <option value="hors-sujet">Hors sujet</option>
                            <option value="qualite-faible">Réponse de faible qualité</option>
                            <option value="autre">Autre</option>
                        </select>
                        <input type="text" name="comment" class="session-input" placeholder="Commentaire visible à la fermeture de la session…" required>
                        <button type="submit" class="btn btn-primary btn-sm">Signaler & commenter</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>

<script>
// ─── Recherche côté client dans la liste ───────────────────────
(function() {
    const input = document.getElementById('student-search');
    if (!input) return;
    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        document.querySelectorAll('#student-list .supervise-row').forEach(row => {
            const hit = !q
                || row.dataset.name.includes(q)
                || row.dataset.model.includes(q)
                || row.dataset.prompt.includes(q);
            row.style.display = hit ? '' : 'none';
        });
    });
})();

// ─── Toggle diff vue cours / libre (placeholder visuel) ────────
(function() {
    const btn = document.getElementById('btn-toggle-diff');
    if (!btn) return;
    btn.addEventListener('click', () => {
        document.getElementById('conversation-view')?.classList.toggle('diff-mode');
        btn.classList.toggle('active');
    });
})();

// ─── Submit du form signalement / commentaire ──────────────────
(function() {
    const form = document.getElementById('flag-form');
    if (!form) return;
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = new FormData(form);
        if (!data.get('prompt_id')) {
            alert('Aucun prompt à commenter.');
            return;
        }
        const res = await fetch('/exam/flag', {
            method: 'POST',
            body: new URLSearchParams(data),
        });
        if (res.ok) location.reload();
        else alert('Erreur lors de l\'enregistrement.');
    });
})();
</script>
