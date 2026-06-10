<?php
/**
 * @var array<string, mixed>      $view       Dashboard data from SessionService::dashboard()
 * @var list<\Domain\Document>    $documents  Documents attached to the session
 */
$documents = $documents ?? [];
// Owner = full control; a read-only responsible (teacher_resources) sees the
// dashboard without any mutating control.
$canManage = $canManage ?? false;
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
            <a href="/sessions/<?= (int) $view['id'] ?>/stats" class="btn">
                <?= icon('chart-line', '', 12) ?> Statistiques
            </a>
            <button type="button" class="btn" id="btn-open-export">
                <?= icon('archive', '', 12) ?> Exporter (JSON)
            </button>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <a href="/sessions" class="btn">Retour à la liste</a>
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
        <?php endif; /* canManage */ ?>
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
            <div style="display:flex;gap:2.5rem;flex-wrap:wrap;">
                <div>
                    <div class="kv-key">prompt max</div>
                    <div class="kv-val mono">
                        <?= $view['maxInputSize'] !== null
                            ? number_format((float) $view['maxInputSize'], 0, ',', ' ') . ' caractères'
                            : 'sans limite' ?>
                    </div>
                </div>
                <div>
                    <div class="kv-key">tokens session</div>
                    <div class="kv-val mono">
                        <?= $view['maxTokens'] !== null
                            ? number_format((float) $view['maxTokens'], 0, ',', ' ') . ' tok'
                            : 'sans limite' ?>
                    </div>
                </div>
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
            <p class="doc-card-hint">
                Joints à la session, consultables par les étudiants inscrits (PDF, Markdown ou TXT — 10 Mo max).
            </p>

            <?php
            $importLabel   = (string) $view['documentsImportLabel'];
            $importEnabled = str_starts_with($importLabel, 'Autorisé');
            ?>
            <div class="doc-setting">
                <span class="kv-key">Import étudiant</span>
                <span class="doc-setting-state <?= $importEnabled ? 'is-on' : 'is-off' ?>"><?= htmlspecialchars($importLabel) ?></span>
            </div>

            <div class="doc-list-head">
                <span class="kv-key">Documents joints<?= $documents !== [] ? ' · ' . count($documents) : '' ?></span>
                <?php if ($canManage): ?>
                    <button type="button" class="btn primary sm" id="btn-open-doc-modal">
                        <?= icon('upload', '', 12) ?> Ajouter
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($documents === []): ?>
                <p class="doc-empty">Aucun document pour l'instant.</p>
            <?php else: ?>
                <div class="doc-list">
                    <?php foreach ($documents as $doc): ?>
                        <div class="doc-item">
                            <div class="doc-item-main">
                                <a href="/documents/session_<?= (int) $doc->sessionId() ?>/<?= (int) $doc->id() ?>"
                                   target="_blank" rel="noopener" class="doc-item-link">
                                    <?= icon('book', '', 13) ?> <?= htmlspecialchars($doc->originalName()) ?>
                                </a>
                                <span class="doc-item-meta">
                                    <?= htmlspecialchars($doc->kindLabel()) ?> · <?= htmlspecialchars($doc->humanSize()) ?> · <?= htmlspecialchars($doc->status()->label()) ?>
                                </span>
                            </div>
                            <?php if ($canManage): ?>
                                <form method="POST" action="/documents/<?= (int) $doc->id() ?>/delete"
                                      class="doc-item-delete" onsubmit="return confirm('Supprimer ce document ?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn bordered sm" title="Supprimer"><?= icon('x-circle', '', 13) ?></button>
                                </form>
                            <?php endif; ?>
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
                    <button type="button" class="btn bordered" data-copy="<?= htmlspecialchars($view['accessCode']) ?>"
                        data-copy-feedback="text">
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
                <label
                    style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-800);padding:4px 0;">
                    <input type="checkbox" name="include_prompts" checked> Prompts des étudiants
                </label>
                <label
                    style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gray-800);padding:4px 0;">
                    <input type="checkbox" name="include_responses" checked> Réponses du LLM
                </label>
            </fieldset>

            <fieldset style="border:1px solid var(--gray-200);border-radius:8px;padding:10px 14px;margin:0 0 18px;">
                <legend style="font-size:12px;font-weight:600;color:var(--gray-600);padding:0 6px;">Étudiants</legend>
                <?php if ($students === []): ?>
                    <p style="font-size:12px;color:var(--gray-400);margin:0;">Aucun étudiant inscrit.</p>
                <?php else: ?>
                    <p style="font-size:11px;color:var(--gray-400);margin:0 0 8px;">Cochez ceux à <strong>exclure</strong>
                        de
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

<!-- Add-documents modal: multi-select + drag & drop (owner only) -->
<?php if ($canManage): ?>
    <div id="modal-documents" class="doc-modal-overlay" style="display:none;">
        <div class="doc-modal-box">
            <h2>Ajouter des documents</h2>
            <p class="doc-modal-sub">PDF, Markdown ou TXT — 10 Mo max. Plusieurs fichiers à la fois.</p>
            <form method="POST" action="/sessions/<?= (int) $view['id'] ?>/documents" enctype="multipart/form-data"
                id="doc-upload-form">
                <?= csrf_field() ?>
                <label class="doc-dropzone" id="doc-dropzone">
                    <input type="file" name="document[]" id="doc-input" multiple
                        accept=".pdf,.md,.markdown,.txt,application/pdf,text/plain,text/markdown">
                    <span class="doc-dropzone-icon"><?= icon('upload', '', 26) ?></span>
                    <span class="doc-dropzone-title">Glissez-déposez vos fichiers ici</span>
                    <span class="doc-dropzone-hint">ou cliquez pour parcourir</span>
                </label>
                <ul class="doc-selected" id="doc-selected"></ul>
                <div class="doc-modal-actions">
                    <button type="button" class="btn" id="btn-cancel-doc">Annuler</button>
                    <button type="submit" class="btn primary" id="btn-submit-doc" disabled>
                        <?= icon('upload', '', 12) ?> Importer
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; /* canManage — add-documents modal */ ?>

<script>
    (function () {
        const openBtn = document.getElementById('btn-open-doc-modal');
        const modal = document.getElementById('modal-documents');
        const cancel = document.getElementById('btn-cancel-doc');
        const dropzone = document.getElementById('doc-dropzone');
        const input = document.getElementById('doc-input');
        const list = document.getElementById('doc-selected');
        const submit = document.getElementById('btn-submit-doc');
        if (!openBtn || !modal) return;

        // Source of truth: survives the browser replacing input.files on each
        // drop / picker selection, so successive imports accumulate.
        let selected = [];

        const show = () => { selected = []; syncInput(); render(); modal.style.display = 'flex'; };
        const hide = () => { modal.style.display = 'none'; };

        function human(b) {
            if (b < 1024) return b + ' o';
            if (b < 1048576) return Math.round(b / 1024) + ' Ko';
            return (b / 1048576).toFixed(1) + ' Mo';
        }

        // Push the accumulated list back onto the input so the form submits them all.
        function syncInput() {
            const dt = new DataTransfer();
            selected.forEach((f) => dt.items.add(f));
            input.files = dt.files;
        }

        // Merge files in (from a drop or the picker), skipping duplicates (name + size).
        function addFiles(incoming) {
            const seen = new Set(selected.map((f) => f.name + ':' + f.size));
            Array.from(incoming).forEach((f) => {
                const key = f.name + ':' + f.size;
                if (!seen.has(key)) { selected.push(f); seen.add(key); }
            });
            syncInput();
            render();
        }

        function removeAt(i) {
            selected.splice(i, 1);
            syncInput();
            render();
        }

        function render() {
            list.innerHTML = '';
            selected.forEach((f, i) => {
                const li = document.createElement('li');
                li.className = 'doc-selected-item';

                const name = document.createElement('span');
                name.className = 'doc-selected-name';
                name.textContent = f.name + '  ·  ' + human(f.size);

                const del = document.createElement('button');
                del.type = 'button';
                del.className = 'doc-selected-remove';
                del.setAttribute('aria-label', 'Retirer ' + f.name);
                del.textContent = '×';
                del.addEventListener('click', () => removeAt(i));

                li.appendChild(name);
                li.appendChild(del);
                list.appendChild(li);
            });
            submit.disabled = selected.length === 0;
        }

        openBtn.addEventListener('click', show);
        cancel?.addEventListener('click', hide);
        modal.addEventListener('click', (e) => { if (e.target === modal) hide(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.style.display === 'flex') hide();
        });

        // Picker selection: the browser just replaced input.files with the new
        // picks — merge them into the accumulated list instead of overwriting.
        input.addEventListener('change', () => addFiles(input.files));

        ['dragenter', 'dragover'].forEach((ev) => dropzone.addEventListener(ev, (e) => {
            e.preventDefault();
            dropzone.classList.add('is-dragover');
        }));
        ['dragleave', 'dragend'].forEach((ev) => dropzone.addEventListener(ev, (e) => {
            e.preventDefault();
            dropzone.classList.remove('is-dragover');
        }));
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('is-dragover');
            if (e.dataTransfer && e.dataTransfer.files.length) addFiles(e.dataTransfer.files);
        });
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