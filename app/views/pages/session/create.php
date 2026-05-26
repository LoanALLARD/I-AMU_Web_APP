<?php
// Classifie le modèle pour le "tag" (chat / code) — heuristique simple sur le nom
$modelTag = function (string $name): string {
    $lc = strtolower($name);
    foreach (['coder', 'code', 'codellama', 'starcoder', 'deepseek-coder', 'qwen.*coder'] as $needle) {
        if (preg_match('/' . $needle . '/i', $lc)) return 'code';
    }
    return 'chat';
};

// Tailles approximatives extraites de la version (ex: "7B" → 7B)
$modelSize = function (?string $version): string {
    if (!$version) return '—';
    return $version;
};
?>
<div class="page-container session-create-page">
    <div class="page-header">
        <h1>Nouvelle session</h1>
        <p class="page-subtitle">
            Une session est un cours ou un examen. Les étudiants la rejoignent
            avec le code à 6 caractères.
        </p>
    </div>

    <form method="POST" action="/sessions/store" class="session-create-layout" id="session-form">
        <input type="hidden" name="access_code" value="<?= htmlspecialchars($previewCode) ?>">

        <!-- ═══ COLONNE GAUCHE : Configuration ═══════════════════ -->
        <div class="session-create-main">

            <!-- Type -->
            <section class="session-section">
                <h2 class="section-title">Type</h2>
                <div class="type-cards">
                    <label class="type-card active">
                        <input type="radio" name="type" value="TP" checked>
                        <div class="type-card-head">
                            <?= icon('message-circle') ?>
                            <strong>Cours</strong>
                        </div>
                        <p class="type-card-desc">
                            Historique scopé, modèles épinglés en haut, étudiants
                            gardent l'accès libre en parallèle.
                        </p>
                    </label>
                    <label class="type-card">
                        <input type="radio" name="type" value="EXAM">
                        <div class="type-card-head">
                            <?= icon('lock') ?>
                            <strong>Examen</strong>
                        </div>
                        <p class="type-card-desc">
                            Pas d'historique, pas de sortie, un seul modèle, surveillance
                            visuelle. Mortise kraft visible de la porte.
                        </p>
                    </label>
                </div>
            </section>

            <!-- Libellé -->
            <section class="session-section">
                <h2 class="section-title">Libellé</h2>
                <input type="text" name="name" id="name" class="session-input"
                       placeholder="Ex: INF302 — Bases de données · TD4" required>
            </section>

            <!-- Planification -->
            <section class="session-section">
                <h2 class="section-title">Planification</h2>
                <div class="session-row-3">
                    <div class="form-group">
                        <label class="session-sublabel" for="starts_at">Démarre</label>
                        <input type="datetime-local" id="starts_at" name="starts_at" class="session-input">
                    </div>
                    <div class="form-group">
                        <label class="session-sublabel" for="duration_min">Durée</label>
                        <div class="input-suffix">
                            <input type="number" id="duration_min" name="duration_min" class="session-input"
                                   value="90" min="5" max="480">
                            <span class="suffix">min</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="session-sublabel" for="auto_close">Auto-clôture</label>
                        <select id="auto_close" name="auto_close" class="session-input">
                            <option value="end">À la fin</option>
                            <option value="manual">Manuelle</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Modèles autorisés -->
            <section class="session-section">
                <div class="section-title-row">
                    <h2 class="section-title">Modèles autorisés</h2>
                    <span class="section-meta">
                        <span id="models-count">0</span> / <?= count($models) ?>
                        <span class="text-success" id="models-ok"><?= icon('check') ?> ok</span>
                    </span>
                </div>
                <p class="account-help">
                    // 3 max recommandés pour ne pas paralyser le choix. Les modèles
                    non cochés sont invisibles côté étudiant.
                </p>
                <div class="model-list">
                    <?php foreach ($models as $i => $model):
                        $tag  = $modelTag($model['name']);
                        $size = $modelSize($model['version'] ?? null);
                    ?>
                        <label class="model-item">
                            <input type="checkbox" name="models[]" value="<?= $model['model_id'] ?>"
                                   <?= $i < 2 ? 'checked' : '' ?>>
                            <span class="model-name"><?= htmlspecialchars($model['name']) ?></span>
                            <span class="model-meta">
                                <span class="model-tag model-tag-<?= $tag ?>"><?= $tag ?></span>
                                <span class="model-size"><?= htmlspecialchars($size) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    <?php if (empty($models)): ?>
                        <p class="text-muted">Aucun modèle disponible. Sync depuis /admin/models.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Pré-prompt -->
            <section class="session-section">
                <h2 class="section-title">Pré-prompt système</h2>
                <textarea name="system_prompt" id="system_prompt" rows="4" class="session-input"
                          placeholder="Ex: Tu aides un étudiant en L2 informatique. Reste factuel. Pas de réponses copiables sans explication."></textarea>
                <small class="form-hint">Envoyé au modèle avant chaque conversation. Visible côté étudiant si la session est de type Cours.</small>
            </section>

            <!-- Instructions étudiants -->
            <section class="session-section">
                <h2 class="section-title">Instructions étudiants</h2>
                <textarea name="instructions" id="instructions" rows="3" class="session-input"
                          placeholder="Instructions affichées dans le panneau étudiant à l'ouverture."></textarea>
            </section>

            <!-- Limites -->
            <section class="session-section">
                <h2 class="section-title">Limites</h2>
                <div class="session-row-3">
                    <div class="form-group">
                        <label class="session-sublabel" for="max_input_size">Taille max prompt</label>
                        <div class="input-suffix">
                            <input type="number" id="max_input_size" name="max_input_size" class="session-input"
                                   value="2000" min="100" max="10000">
                            <span class="suffix">caractères</span>
                        </div>
                    </div>
                </div>
            </section>

            <div class="form-actions session-actions">
                <a href="/sessions" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary">Créer la session</button>
            </div>
        </div>

        <!-- ═══ COLONNE DROITE : Code & preview ══════════════════ -->
        <aside class="session-create-aside">
            <!-- Code d'accès -->
            <div class="access-code-box">
                <div class="access-code-label">Code d'accès</div>
                <div class="access-code-value" id="access-code-display">
                    <?= htmlspecialchars(substr($previewCode, 0, 3)) ?>-<?= htmlspecialchars(substr($previewCode, 3, 3)) ?>
                </div>
                <p class="access-code-hint">
                    Généré à la création. À inscrire au tableau, dicter à l'oral, ou afficher sur la slide.
                </p>
                <div class="access-code-actions">
                    <button type="button" class="btn btn-secondary btn-sm" id="btn-copy-code">
                        Copier
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="btn-fullscreen-code">
                        Plein écran
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="btn-qr-code" disabled title="Bientôt">
                        QR
                    </button>
                </div>
            </div>

            <!-- Aperçu côté étudiant -->
            <div class="preview-box">
                <h3 class="preview-title">Aperçu côté étudiant</h3>
                <div class="preview-card">
                    <div class="preview-card-head">
                        <span class="preview-pill" id="preview-type">Cours</span>
                        <code class="preview-code"><?= htmlspecialchars($previewCode) ?></code>
                    </div>
                    <div class="preview-card-name" id="preview-name">— libellé —</div>
                    <div class="preview-card-meta">
                        <?= htmlspecialchars($_SESSION['user_first_name'] ?? '') ?>
                        <?= htmlspecialchars($_SESSION['user_last_name'] ?? '') ?>
                        · <span id="preview-duration">90</span> min
                    </div>
                    <ul class="preview-models" id="preview-models"></ul>
                    <div class="preview-prompt" id="preview-prompt" hidden>
                        <span class="preview-prompt-label"># pré-prompt visible</span>
                        <p id="preview-prompt-text"></p>
                    </div>
                </div>
            </div>

            <!-- Checklist -->
            <div class="checklist-box">
                <h3 class="preview-title">Avant de créer</h3>
                <ul class="checklist">
                    <li class="checklist-item <?= $ollamaAvailable ? 'ok' : 'ko' ?>">
                        <?= $ollamaAvailable ? icon('check', 'text-success') : icon('x', 'text-error') ?>
                        <?= $ollamaModelsCount ?> modèle<?= $ollamaModelsCount > 1 ? 's' : '' ?>
                        disponible<?= $ollamaModelsCount > 1 ? 's' : '' ?> sur Ollama
                    </li>
                    <li class="checklist-item ok">
                        <?= icon('check', 'text-success') ?>
                        Charge serveur · <span class="text-muted">OK</span>
                    </li>
                    <li class="checklist-item" id="check-selection">
                        <?= icon('check', 'text-success') ?>
                        <span id="check-selection-text">Sélection de modèles cohérente</span>
                    </li>
                </ul>
            </div>
        </aside>
    </form>
</div>

<script>
(function() {
    const form = document.getElementById('session-form');
    if (!form) return;

    // ─── Type cards ─────────────────────────────────────────────────
    document.querySelectorAll('.type-card').forEach(card => {
        card.addEventListener('click', () => {
            document.querySelectorAll('.type-card').forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            const radio = card.querySelector('input[type=radio]');
            if (radio) {
                radio.checked = true;
                document.getElementById('preview-type').textContent =
                    radio.value === 'EXAM' ? 'Examen' : 'Cours';
            }
        });
    });

    // ─── Mise à jour du preview en temps réel ───────────────────────
    const nameInput     = document.getElementById('name');
    const durationInput = document.getElementById('duration_min');
    const promptInput   = document.getElementById('system_prompt');

    nameInput.addEventListener('input', e => {
        document.getElementById('preview-name').textContent = e.target.value || '— libellé —';
    });
    durationInput.addEventListener('input', e => {
        document.getElementById('preview-duration').textContent = e.target.value || '90';
    });
    promptInput.addEventListener('input', e => {
        const box = document.getElementById('preview-prompt');
        const txt = document.getElementById('preview-prompt-text');
        if (e.target.value.trim()) {
            box.hidden = false;
            txt.textContent = e.target.value.trim();
        } else {
            box.hidden = true;
        }
    });

    // ─── Modèles : compte sélectionnés + preview liste ─────────────
    function refreshModels() {
        const checked = document.querySelectorAll('.model-item input[type=checkbox]:checked');
        document.getElementById('models-count').textContent = checked.length;

        const ul = document.getElementById('preview-models');
        ul.innerHTML = '';
        checked.forEach(cb => {
            const name = cb.parentElement.querySelector('.model-name').textContent;
            const li = document.createElement('li');
            li.textContent = name;
            ul.appendChild(li);
        });

        // Checklist : >0 modèles
        const checkItem = document.getElementById('check-selection');
        const checkText = document.getElementById('check-selection-text');
        if (checked.length === 0) {
            checkItem.classList.remove('ok');
            checkItem.classList.add('ko');
            checkText.textContent = 'Sélectionne au moins un modèle';
        } else if (checked.length > 3) {
            checkItem.classList.remove('ok', 'ko');
            checkItem.classList.add('warn');
            checkText.textContent = checked.length + ' modèles — > 3 recommandés';
        } else {
            checkItem.classList.add('ok');
            checkItem.classList.remove('ko', 'warn');
            checkText.textContent = checked.length + ' modèle(s) sélectionné(s)';
        }
    }
    document.querySelectorAll('.model-item input[type=checkbox]').forEach(cb =>
        cb.addEventListener('change', refreshModels)
    );
    refreshModels();

    // ─── Bouton copier le code ──────────────────────────────────────
    const code = '<?= htmlspecialchars($previewCode) ?>';
    document.getElementById('btn-copy-code').addEventListener('click', () => {
        navigator.clipboard.writeText(code).then(() => {
            const btn = document.getElementById('btn-copy-code');
            const oldText = btn.textContent;
            btn.textContent = 'Copié';
            setTimeout(() => btn.textContent = oldText, 1500);
        });
    });

    // ─── Plein écran du code ───────────────────────────────────────
    document.getElementById('btn-fullscreen-code').addEventListener('click', () => {
        const overlay = document.createElement('div');
        overlay.className = 'code-overlay';
        overlay.innerHTML = `<div class="code-overlay-content"><div class="code-overlay-value">${code}</div><button class="btn btn-secondary code-overlay-close">Fermer (Esc)</button></div>`;
        document.body.appendChild(overlay);
        const close = () => overlay.remove();
        overlay.querySelector('.code-overlay-close').addEventListener('click', close);
        overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
        document.addEventListener('keydown', function onEsc(e) {
            if (e.key === 'Escape') { close(); document.removeEventListener('keydown', onEsc); }
        });
    });
})();
</script>
