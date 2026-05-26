<?php
// Helpers réutilisés du formulaire de création.
$modelTag = function (string $name): string {
    $lc = strtolower($name);
    foreach (['coder', 'code', 'codellama', 'starcoder', 'deepseek-coder', 'qwen.*coder'] as $needle) {
        if (preg_match('/' . $needle . '/i', $lc)) return 'code';
    }
    return 'chat';
};
$modelSize = function (?string $version): string {
    return $version ?: '—';
};

// Pré-formatage des dates pour les inputs datetime-local (format: Y-m-d\TH:i).
$startsAtInput = $session['starts_at']
    ? date('Y-m-d\TH:i', strtotime($session['starts_at']))
    : '';

// Calcul de la durée actuelle (en minutes) si starts_at et ends_at sont définis.
$durationMin = 90;
if (!empty($session['starts_at']) && !empty($session['ends_at'])) {
    $durationMin = max(5, (int) round(
        (strtotime($session['ends_at']) - strtotime($session['starts_at'])) / 60
    ));
}

$currentType  = $session['type'] ?? 'TP';
$accessCode   = $session['access_code'] ?? '';
$authorizedSet = array_flip($authorizedModelIds ?? []);
?>
<div class="page-container session-create-page">
    <div class="page-header">
        <h1>Modifier la session</h1>
        <p class="page-subtitle">
            Les modifications ne sont possibles que tant que la session n'a pas
            commencé. Le code d'accès ne change pas.
        </p>
    </div>

    <form method="POST" action="/sessions/<?= (int) $session['session_id'] ?>/update" class="session-create-layout" id="session-form">

        <!-- ═══ COLONNE GAUCHE : Configuration ═══════════════════ -->
        <div class="session-create-main">

            <!-- Type -->
            <section class="session-section">
                <h2 class="section-title">Type</h2>
                <div class="type-cards">
                    <label class="type-card <?= $currentType === 'TP' ? 'active' : '' ?>">
                        <input type="radio" name="type" value="TP" <?= $currentType === 'TP' ? 'checked' : '' ?>>
                        <div class="type-card-head">
                            <?= icon('message-circle') ?>
                            <strong>Cours</strong>
                        </div>
                        <p class="type-card-desc">
                            Historique scopé, modèles épinglés en haut, étudiants
                            gardent l'accès libre en parallèle.
                        </p>
                    </label>
                    <label class="type-card <?= $currentType === 'EXAM' ? 'active' : '' ?>">
                        <input type="radio" name="type" value="EXAM" <?= $currentType === 'EXAM' ? 'checked' : '' ?>>
                        <div class="type-card-head">
                            <?= icon('lock') ?>
                            <strong>Examen</strong>
                        </div>
                        <p class="type-card-desc">
                            Pas d'historique, pas de sortie, un seul modèle, surveillance
                            visuelle.
                        </p>
                    </label>
                </div>
            </section>

            <!-- Libellé -->
            <section class="session-section">
                <h2 class="section-title">Libellé</h2>
                <input type="text" name="name" id="name" class="session-input"
                       value="<?= htmlspecialchars($session['name'] ?? '') ?>" required>
            </section>

            <!-- Planification -->
            <section class="session-section">
                <h2 class="section-title">Planification</h2>
                <div class="session-row-3">
                    <div class="form-group">
                        <label class="session-sublabel" for="starts_at">Démarre</label>
                        <input type="datetime-local" id="starts_at" name="starts_at" class="session-input"
                               value="<?= htmlspecialchars($startsAtInput) ?>">
                    </div>
                    <div class="form-group">
                        <label class="session-sublabel" for="duration_min">Durée</label>
                        <div class="input-suffix">
                            <input type="number" id="duration_min" name="duration_min" class="session-input"
                                   value="<?= (int) $durationMin ?>" min="5" max="480">
                            <span class="suffix">min</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Modèles autorisés -->
            <section class="session-section">
                <div class="section-title-row">
                    <h2 class="section-title">Modèles autorisés</h2>
                    <span class="section-meta">
                        <span id="models-count">0</span> / <?= count($models) ?>
                    </span>
                </div>
                <div class="model-list">
                    <?php foreach ($models as $model):
                        $tag  = $modelTag($model['name']);
                        $size = $modelSize($model['version'] ?? null);
                        $checked = isset($authorizedSet[$model['model_id']]);
                    ?>
                        <label class="model-item">
                            <input type="checkbox" name="models[]" value="<?= $model['model_id'] ?>"
                                   <?= $checked ? 'checked' : '' ?>>
                            <span class="model-name"><?= htmlspecialchars($model['name']) ?></span>
                            <span class="model-meta">
                                <span class="model-tag model-tag-<?= $tag ?>"><?= $tag ?></span>
                                <span class="model-size"><?= htmlspecialchars($size) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    <?php if (empty($models)): ?>
                        <p class="text-muted">Aucun modèle disponible.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Pré-prompt -->
            <section class="session-section">
                <h2 class="section-title">Pré-prompt système</h2>
                <textarea name="system_prompt" id="system_prompt" rows="4" class="session-input"
                          placeholder="Pré-prompt envoyé au modèle"><?= htmlspecialchars($session['system_prompt_override'] ?? '') ?></textarea>
            </section>

            <!-- Instructions étudiants -->
            <section class="session-section">
                <h2 class="section-title">Instructions étudiants</h2>
                <textarea name="instructions" id="instructions" rows="3" class="session-input"
                          placeholder="Instructions affichées dans le panneau étudiant à l'ouverture."><?= htmlspecialchars($session['instructions'] ?? '') ?></textarea>
            </section>

            <!-- Limites -->
            <section class="session-section">
                <h2 class="section-title">Limites</h2>
                <div class="session-row-3">
                    <div class="form-group">
                        <label class="session-sublabel" for="max_input_size">Taille max prompt</label>
                        <div class="input-suffix">
                            <input type="number" id="max_input_size" name="max_input_size" class="session-input"
                                   value="<?= (int) ($session['max_input_size'] ?? 2000) ?>" min="100" max="10000">
                            <span class="suffix">caractères</span>
                        </div>
                    </div>
                </div>
            </section>

            <div class="form-actions session-actions">
                <a href="/sessions" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </div>

        <!-- ═══ COLONNE DROITE : Code d'accès (lecture seule) ════ -->
        <aside class="session-create-aside">
            <div class="access-code-box">
                <div class="access-code-label">Code d'accès</div>
                <div class="access-code-value">
                    <?= htmlspecialchars($accessCode) ?>
                </div>
                <p class="access-code-hint">
                    Le code reste identique entre les modifications : les étudiants
                    qui l'ont déjà noté peuvent toujours rejoindre.
                </p>
                <div class="access-code-actions">
                    <button type="button" class="btn btn-secondary btn-sm" id="btn-copy-code-edit">
                        Copier
                    </button>
                </div>
            </div>
        </aside>
    </form>
</div>

<script>
(function() {
    // Type cards : active class follow radio
    document.querySelectorAll('.type-card').forEach(card => {
        card.addEventListener('click', () => {
            document.querySelectorAll('.type-card').forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            const radio = card.querySelector('input[type=radio]');
            if (radio) radio.checked = true;
        });
    });

    // Compteur modèles
    function refreshModels() {
        const checked = document.querySelectorAll('.model-item input[type=checkbox]:checked');
        document.getElementById('models-count').textContent = checked.length;
    }
    document.querySelectorAll('.model-item input[type=checkbox]').forEach(cb =>
        cb.addEventListener('change', refreshModels)
    );
    refreshModels();

    // Bouton copier le code d'accès
    const btnCopy = document.getElementById('btn-copy-code-edit');
    const code = <?= json_encode($accessCode) ?>;
    if (btnCopy) {
        btnCopy.addEventListener('click', () => {
            navigator.clipboard.writeText(code).then(() => {
                const old = btnCopy.textContent;
                btnCopy.textContent = 'Copié ✓';
                setTimeout(() => btnCopy.textContent = old, 1500);
            });
        });
    }
})();
</script>
