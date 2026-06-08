<?php
/**
 * @var array<string, mixed>      $view       Dashboard data from SessionService::dashboard()
 * @var list<\Domain\Document>    $documents  Documents attached to the session
 */
$documents = $documents ?? [];
?>
<div class="dashboard-header">
    <div>
        <h1 style="margin:0;font-size:24px;font-weight:600;letter-spacing:-0.02em;">
            <?= htmlspecialchars($view['name']) ?>
        </h1>
        <div class="dashboard-meta">
            <span
                class="badge <?= htmlspecialchars($view['typeClass']) ?>"><?= htmlspecialchars($view['typeLabel']) ?></span>
            <span
                class="badge <?= htmlspecialchars($view['statusClass']) ?>"><?= htmlspecialchars($view['statusLabel']) ?></span>
            <?php if ($view['accessCode'] !== ''): ?>
                <code class="access-code-cell" style="font-size:13px;color:var(--gray-600);">
                        <?= htmlspecialchars($view['accessCode']) ?>
                    </code>
            <?php endif; ?>
        </div>
    </div>
    <div class="dashboard-actions">
        <?php if (!empty($view['canMonitor'])): ?>
            <a href="/sessions/<?= (int) $view['id'] ?>/monitor" class="btn primary">
                <?= icon('user', '', 12) ?> Suivi
            </a>
            <button type="button" class="btn" id="btn-open-export">
                <?= icon('archive', '', 12) ?> Exporter (JSON)
            </button>
        <?php endif; ?>
        <?php if ($view['canEdit']): ?>
            <a href="/sessions/<?= (int) $view['id'] ?>/edit" class="btn">
                <?= icon('edit', '', 12) ?> Modifier
            </a>
        <?php endif; ?>
        <?php if ($view['canStart']): ?>
            <form method="POST" action="/sessions/<?= (int) $view['id'] ?>/start" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn success">
                    <?= icon('play', '', 12) ?> Démarrer
                </button>
            </form>
        <?php endif; ?>
        <?php if ($view['canEnd']): ?>
            <form method="POST" action="/sessions/<?= (int) $view['id'] ?>/end" style="margin:0;"
                onsubmit="return confirm('Terminer cette session maintenant ?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn">
                    <?= icon('square', '', 12) ?> Terminer
                </button>
            </form>
        <?php endif; ?>
        <?php if ($view['canCancel']): ?>
            <form method="POST" action="/sessions/<?= (int) $view['id'] ?>/cancel" style="margin:0;"
                onsubmit="return confirm('Annuler cette session ?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn danger">
                    <?= icon('x-circle', '', 12) ?> Annuler
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="dashboard-grid">
    <div>
        <div class="dashboard-card">
            <h2>Planning</h2>
            <div class="kv-grid">
                <span class="kv-key">démarrage</span>
                <span class="kv-val"><?= htmlspecialchars($view['startsAtFormatted'] ?? '— non planifié') ?></span>
                <span class="kv-key">fin</span>
                <span class="kv-val"><?= htmlspecialchars($view['endsAtFormatted'] ?? '— non planifié') ?></span>
                <?php if ($view['closedAtFormatted']): ?>
                    <span class="kv-key">clôturée le</span>
                    <span class="kv-val"><?= htmlspecialchars($view['closedAtFormatted']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-card">
            <h2>Limites</h2>
            <div class="kv-grid">
                <span class="kv-key">prompt max</span>
                <span class="kv-val mono">
                    <?= $view['maxInputSize'] !== null
                        ? number_format((float) $view['maxInputSize'], 0, ',', ' ') . ' caractères'
                        : 'sans limite' ?>
                </span>
                </span>
                <span class="kv-key">tokens session</span>
                <span class="kv-val mono">
                    <?= $view['maxTokens'] !== null
                    ? number_format((float) $view['maxTokens'], 0, ',', ' ') . ' tok'
                    : 'sans limite' ?>
                </span>
            </div>
        </div>

        <?php if ($view['prePromptOverride'] !== null || $view['postPromptOverride'] !== null || $view['instructions'] !== null): ?>
            <div class="dashboard-card">
                <h2>Prompts</h2>
                <?php if ($view['prePromptOverride'] !== null): ?>
                    <p class="kv-key" style="margin: 4px 0 6px;">pré-prompt (visible étudiant)</p>
                    <div class="prompt-block"><?= htmlspecialchars($view['prePromptOverride']) ?></div>
                <?php endif; ?>
                <?php if ($view['postPromptOverride'] !== null): ?>
                    <p class="kv-key" style="margin: 14px 0 6px;">post-prompt (invisible étudiant)</p>
                    <div class="prompt-block"><?= htmlspecialchars($view['postPromptOverride']) ?></div>
                <?php endif; ?>
                <?php if ($view['instructions'] !== null): ?>
                    <p class="kv-key" style="margin: 14px 0 6px;">instructions</p>
                    <div class="prompt-block"><?= htmlspecialchars($view['instructions']) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-card">
            <h2>Documents</h2>
            <p class="kv-key" style="margin:2px 0 12px;line-height:1.5;">
                Joints à la session et consultables par les étudiants inscrits.
                PDF, Markdown ou TXT — 10 Mo max.
            </p>

            <form method="POST" action="/sessions/<?= (int) $view['id'] ?>/documents"
                  enctype="multipart/form-data"
                  style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <?= csrf_field() ?>
                <input type="file" name="document" required
                       accept=".pdf,.md,.markdown,.txt,application/pdf,text/plain,text/markdown"
                       style="flex:1;min-width:180px;font-size:12px;">
                <button type="submit" class="btn primary"><?= icon('upload', '', 12) ?> Ajouter</button>
            </form>

            <?php if ($documents === []): ?>
                <p style="color:var(--gray-400);font-size:12px;margin-top:12px;">Aucun document pour l'instant.</p>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:6px;margin-top:14px;">
                    <?php foreach ($documents as $doc): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;border:1px solid var(--gray-200);border-radius:8px;">
                            <div style="min-width:0;">
                                <a href="/documents/session_<?= (int) $doc->sessionId() ?>/<?= (int) $doc->id() ?>" target="_blank" rel="noopener"
                                   style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--gray-800);text-decoration:none;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= icon('book', '', 13) ?> <?= htmlspecialchars($doc->originalName()) ?>
                                </a>
                                <span style="font-size:11px;color:var(--gray-400);">
                                    <?= htmlspecialchars($doc->kindLabel()) ?> · <?= htmlspecialchars($doc->humanSize()) ?> · <?= htmlspecialchars($doc->status()->label()) ?>
                                </span>
                            </div>
                            <form method="POST" action="/documents/<?= (int) $doc->id() ?>/delete"
                                  style="margin:0;flex-shrink:0;"
                                  onsubmit="return confirm('Supprimer ce document ?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn bordered" title="Supprimer"
                                        style="padding:4px 8px;"><?= icon('x-circle', '', 13) ?></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside>
        <?php if ($view['accessCode'] !== ''): ?>
            <div class="dashboard-card">
                <h2>Code d'accès</h2>
                <div class="access-code-display" style="font-size: 38px; margin: 4px 0 12px;">
                    <?= htmlspecialchars($view['accessCode']) ?>
                </div>
                <div class="access-card-actions">
                    <button type="button" class="btn bordered"
                            data-copy="<?= htmlspecialchars($view['accessCode']) ?>" data-copy-feedback="text">
                        <?= icon('copy', '', 11) ?> Copier
                    </button>
                    <button type="button" class="btn bordered" id="btn-fullscreen-code">
                        <?= icon('eye', '', 11) ?> Plein écran
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="dashboard-card">
            <h2>Modèles autorisés</h2>
            <?php if ($view['authorizedModels'] === []): ?>
                <p style="color:var(--gray-400);font-size:12px;">Aucun modèle autorisé.</p>
            <?php else: ?>
                <div class="preview-models">
                    <?php foreach ($view['authorizedModels'] as $m): ?>
                        <div class="preview-model">
                            <span><?= htmlspecialchars((string) $m['name']) ?></span>
                            <?php if (!empty($m['size'])): ?>
                                <span style="color:var(--gray-400);">· <?= htmlspecialchars((string) $m['size']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php $students = $students ?? []; ?>
<div id="modal-export"
    style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div
        style="background:var(--white);border:1px solid var(--gray-200);border-radius:12px;padding:24px 28px;max-width:520px;width:92%;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <h2 style="margin:0 0 6px;font-size:18px;color:var(--gray-800);">Exporter les données (JSON)</h2>
        <p style="font-size:12px;color:var(--gray-500);margin:0 0 18px;line-height:1.5;">
            Par défaut, l'intégralité des données de la session est exportée. Ajustez ci-dessous.
        </p>

        <form method="GET" action="/sessions/<?= (int) $view['id'] ?>/export">
            <input type="hidden" name="configured" value="1">

            <fieldset style="border:1px solid var(--gray-200);border-radius:8px;padding:10px 14px;margin:0 0 14px;">
                <legend style="font-size:12px;font-weight:600;color:var(--gray-600);padding:0 6px;">Contenu</legend>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-800);padding:4px 0;">
                    <input type="checkbox" name="include_prompts" checked> Prompts des étudiants
                </label>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-800);padding:4px 0;">
                    <input type="checkbox" name="include_responses" checked> Réponses du LLM
                </label>
            </fieldset>

            <fieldset style="border:1px solid var(--gray-200);border-radius:8px;padding:10px 14px;margin:0 0 18px;">
                <legend style="font-size:12px;font-weight:600;color:var(--gray-600);padding:0 6px;">Étudiants</legend>
                <?php if ($students === []): ?>
                    <p style="font-size:12px;color:var(--gray-400);margin:0;">Aucun étudiant inscrit.</p>
                <?php else: ?>
                    <p style="font-size:11px;color:var(--gray-400);margin:0 0 8px;">Cochez ceux à <strong>exclure</strong> de
                        l'export.</p>
                    <div style="display:flex;flex-direction:column;gap:2px;max-height:200px;overflow-y:auto;">
                        <?php foreach ($students as $st): ?>
                            <label
                                style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-800);padding:3px 0;">
                                <input type="checkbox" name="exclude_ids[]" value="<?= (int) $st['id'] ?>">
                                <?= htmlspecialchars(trim(($st['last_name'] ?? '') . ' ' . ($st['first_name'] ?? ''))) ?>
                                <?php if (!empty($st['student_number'])): ?>
                                    <span class="mono"
                                        style="font-size:11px;color:var(--gray-400);">#<?= htmlspecialchars((string) $st['student_number']) ?></span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </fieldset>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn" id="btn-cancel-export"
                    style="background:var(--gray-100);color:var(--gray-700);">Annuler</button>
                <button type="submit" class="btn primary"><?= icon('archive', '', 12) ?> Télécharger</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const open = document.getElementById('btn-open-export');
        const modal = document.getElementById('modal-export');
        const cancel = document.getElementById('btn-cancel-export');
        if (!open || !modal) return;

        const show = () => { modal.style.display = 'flex'; };
        const hide = () => { modal.style.display = 'none'; };

        open.addEventListener('click', show);
        cancel?.addEventListener('click', hide);
        modal.addEventListener('click', (e) => { if (e.target === modal) hide(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.style.display === 'flex') hide();
        });
        // The download response keeps the current page; close the modal shortly
        // after submit for a clean return.
        modal.querySelector('form')?.addEventListener('submit', () => setTimeout(hide, 400));
    })();
</script>

<script>
    window.__IAMU_SESSION_DASHBOARD__ = {
        code: <?= json_encode($view['accessCode']) ?>
    };
</script>
<?php
$jsPath = __DIR__ . '/../../../../public/assets/js/session-create.js';
$jsVer = is_file($jsPath) ? filemtime($jsPath) : 0;
?>
<script src="/assets/js/session-create.js?v=<?= $jsVer ?>" defer></script>