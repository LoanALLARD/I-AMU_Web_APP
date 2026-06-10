<?php
/**
 * Create / Edit session form.
 *
 * @var string                       $mode                 'create' | 'edit'
 * @var \Domain\Session|null         $session              Existing entity in edit mode
 * @var list<array<string,mixed>>    $models               All active LLM models
 * @var list<array<string,mixed>>    $resources            Resources owned by the teacher
 * @var int[]                        $authorizedModelIds   Pre-checked model ids (edit mode)
 * @var string                       $previewCode          Raw 6-char code (right column)
 * @var string                       $previewCodeFormatted Code formatted as XXX-XXX
 * @var array<string,mixed>          $oldInput             Re-fill values on validation error
 */

use Domain\SessionType;

$isEdit = $mode === 'edit';
$action = $isEdit ? '/sessions/' . $session->id() . '/update' : '/sessions/store';

$val = static function (string $key, mixed $default = '') use ($session, $oldInput, $isEdit) {
    if (array_key_exists($key, $oldInput)) {
        return is_scalar($oldInput[$key]) ? (string) $oldInput[$key] : $default;
    }
    if (!$isEdit || $session === null) {
        return $default;
    }
    return match ($key) {
        'name'           => $session->name(),
        'pre_prompt'     => $session->prePromptOverride() ?? '',
        'post_prompt'    => $session->postPromptOverride() ?? '',
        'instructions'   => $session->instructions() ?? '',
        'max_input_size' => $session->maxInputSize() ?? '',
        'max_tokens'     => $session->maxTokens() ?? '',
        'documents_max_mb' => $session->documentsMaxBytes() !== null ? (int) ($session->documentsMaxBytes() / 1024 / 1024) : '',
        'resource_id'    => $session->resourceId(),
        'starts_at'      => $session->startsAt()?->format('Y-m-d\TH:i') ?? '',
        'duration_min'   => $session->startsAt() && $session->endsAt()
                                ? (int) (($session->endsAt()->getTimestamp() - $session->startsAt()->getTimestamp()) / 60)
                                : 90,
        default          => $default,
    };
};

// Document settings — the authorised file formats are a multi-select; an empty
// set means student imports are disabled. Resolved outside $val (not a scalar).
$availableFormats = $availableFormats ?? ['pdf', 'md', 'txt'];
$hasOldInput = $oldInput !== [];
// Master toggle state: from the resubmitted form, else "has authorised formats".
$docsEnabled = $hasOldInput
    ? array_key_exists('documents_enabled', $oldInput)
    : ($authorizedFormats ?? []) !== [];
$selectedDocTypes = $hasOldInput
    ? array_map('strval', (array) ($oldInput['documents_types'] ?? []))
    : ($authorizedFormats ?? []);

$currentType = $session?->type() ?? SessionType::Tutorial;
if (isset($oldInput['type'])) {
    $t = SessionType::tryFrom((string) $oldInput['type']);
    if ($t !== null) {
        $currentType = $t;
    }
}
$selectedResourceId = (int) $val('resource_id', 0);

$typeCards = [
    SessionType::Tutorial->value  => ['icon' => 'book',  'label' => 'TD',          'desc' => 'Travaux dirigés guidés, historique scopé, prompts visibles.', 'kraft' => false],
    SessionType::Lab->value       => ['icon' => 'book',  'label' => 'TP',          'desc' => 'Travail pratique en salle, plusieurs modèles autorisés.',     'kraft' => false],
    SessionType::FreeStudy->value => ['icon' => 'eye',   'label' => 'Étude libre', 'desc' => 'Exploration ouverte sans cadre d\'examen.',                   'kraft' => false],
    SessionType::Exam->value      => ['icon' => 'lock',  'label' => 'Examen',      'desc' => 'Pas d\'historique, surveillance visuelle, papier kraft.',     'kraft' => true],
];
?>
<div class="page-header">
    <div class="page-header-row">
        <h1><?= $isEdit ? 'Modifier la session' : 'Nouvelle session' ?></h1>
        <span class="mono page-header-hint">
            <?= $isEdit ? 'édition' : 'brouillon · saisie' ?>
        </span>
    </div>
    <p class="page-sub">Une session est rattachée à un cours (resource). Les étudiants la rejoignent avec le code à 6 caractères.</p>
</div>

<form method="POST" action="<?= htmlspecialchars($action) ?>" class="session-form-grid" id="session-form">
    <?= csrf_field() ?>

    <div class="session-form-main">

        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Type de session</span>
                <span class="fsection-rule"></span>
            </div>
            <div class="kind-grid">
                <?php foreach ($typeCards as $value => $c):
                    $active = $currentType->value === $value;
                    ?>
                    <label class="kind-card <?= $c['kraft'] ? 'kraft' : '' ?> <?= $active ? 'is-active' : '' ?>" data-kind="<?= htmlspecialchars($value) ?>">
                        <input type="radio" name="type" value="<?= htmlspecialchars($value) ?>"
                            <?= $active ? 'checked' : '' ?> <?= $isEdit ? 'disabled' : '' ?>>
                        <span class="kind-head"><?= icon($c['icon'], '', 15) ?> <?= htmlspecialchars($c['label']) ?></span>
                        <span class="kind-desc"><?= htmlspecialchars($c['desc']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if ($isEdit): ?>
                <p class="fsection-hint">Le type ne peut pas être modifié après création.</p>
            <?php endif; ?>
        </section>

        <?php if (!$isEdit): ?>
            <section class="fsection">
                <div class="fsection-head">
                    <span class="fsection-label">Ressource (cours)</span>
                    <span class="fsection-rule"></span>
                </div>
                <?php if ($resources === []): ?>
                    <p class="fsection-hint">Aucune ressource ne vous appartient. Demandez à un administrateur de département de vous en attribuer une avant de créer une session.</p>
                <?php else: ?>
                    <select name="resource_id" required>
                        <option value="">— Sélectionnez une ressource —</option>
                        <?php foreach ($resources as $r): ?>
                            <option value="<?= (int) $r['id'] ?>" <?= $selectedResourceId === (int) $r['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $r['code']) ?> — <?= htmlspecialchars((string) $r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Libellé</span>
                <span class="fsection-rule"></span>
            </div>
            <input type="text" name="name" id="f-name"
                   value="<?= htmlspecialchars((string) $val('name')) ?>"
                   placeholder="INF302 — Bases de données · TD4" required maxlength="255">
        </section>

        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Planification</span>
                <span class="fsection-rule"></span>
            </div>
            <div class="row-2">
                <div>
                    <label class="flabel" for="f-starts">démarrage</label>
                    <input type="datetime-local" id="f-starts" name="starts_at"
                           value="<?= htmlspecialchars((string) $val('starts_at')) ?>">
                </div>
                <div>
                    <label class="flabel" for="f-duration">durée</label>
                    <div class="field-suffix">
                        <input type="number" id="f-duration" name="duration_min" min="5" max="480"
                               value="<?= htmlspecialchars((string) $val('duration_min', 90)) ?>">
                        <span class="suffix">min</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Modèles autorisés</span>
                <span class="fsection-rule"></span>
                <span class="fsection-extra"><span id="models-count">0</span> / <?= count($models) ?></span>
            </div>
            <p class="fsection-hint">3 max recommandés. Les modèles non cochés sont invisibles côté étudiant.</p>
            <div class="models-list">
                <?php foreach ($models as $i => $m):
                    $checked = in_array((int) $m['id'], $authorizedModelIds, true) || (!$isEdit && $i < 2);
                ?>
                    <label class="model-row <?= $checked ? 'is-checked' : 'is-unchecked' ?>">
                        <input type="checkbox" name="models[]" value="<?= (int) $m['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                        <span class="model-name"><?= htmlspecialchars((string) $m['name']) ?></span>
                        <span class="model-size">
                            <?= htmlspecialchars((string) ($m['version'] ?? '—')) ?>
                            <?php if (!empty($m['context_window'])): ?>
                                · ctx <?= number_format((float) $m['context_window'] / 1000, 0) ?>k
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Pré-prompt</span>
                <span class="fsection-rule"></span>
            </div>
            <p class="fsection-hint">Injecté en tête de chaque conversation. Visible par l'étudiant. Markdown autorisé.</p>
            <textarea name="pre_prompt" id="f-prompt" placeholder="Vous aidez un étudiant en L2 informatique. Restez factuel."><?= htmlspecialchars((string) $val('pre_prompt')) ?></textarea>
            <div class="prompt-meta">
                <span><span id="prompt-chars">0</span> chars · ≈ <span id="prompt-tokens">0</span> tokens</span>
                <span>markdown · visible étudiant</span>
            </div>
        </section>

        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Post-prompt</span>
                <span class="fsection-rule"></span>
            </div>
            <p class="fsection-hint">Suffixe injecté juste avant l'appel LLM. Invisible côté étudiant.</p>
            <textarea name="post_prompt" placeholder="(optionnel)"><?= htmlspecialchars((string) $val('post_prompt')) ?></textarea>
        </section>

        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Instructions affichées</span>
                <span class="fsection-rule"></span>
            </div>
            <p class="fsection-hint">Texte d'accompagnement affiché en haut de l'écran étudiant.</p>
            <textarea name="instructions" placeholder="(optionnel)"><?= htmlspecialchars((string) $val('instructions')) ?></textarea>
        </section>

        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Limite par prompt</span>
                <span class="fsection-rule"></span>
            </div>
            <p class="fsection-hint">Taille maximale d'un prompt étudiant. Laisser vide pour aucune limite.</p>
            <div class="field-suffix field-suffix--narrow">
                <input type="number" id="f-max" name="max_input_size" min="1" max="200000"
                       value="<?= htmlspecialchars((string) $val('max_input_size')) ?>"
                       placeholder="ex. 8192">
                <span class="suffix">car</span>
            </div>
        </section>

        <!-- Per-session request quota -->
        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Limite de tokens</span>
                <span class="fsection-rule"></span>
            </div>
            <p class="fsection-hint">Nombre maximal de tokens qu'un étudiant peut utiliser sur la session. Laisser vide pour aucune limite.</p>
            <div class="field-suffix field-suffix--narrow">
                <input type="number" id="f-maxreq" name="max_tokens" min="1" max="10000"
                       value="<?= htmlspecialchars((string) $val('max_tokens')) ?>"
                       placeholder="ex. 20">
                <span class="suffix">tok</span>
            </div>
        </section>

        <!-- Document import settings (apply to this session's chats) -->
        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Documents</span>
                <span class="fsection-rule"></span>
            </div>
            <p class="fsection-hint">Import de documents par les étudiants dans les chats de cette session.</p>

            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                <input type="checkbox" name="documents_enabled" value="1" id="f-docs-enabled" <?= $docsEnabled ? 'checked' : '' ?>>
                <span>Autoriser l'import de documents</span>
            </label>

            <div id="docs-options" style="margin-top:.7rem;<?= $docsEnabled ? '' : 'opacity:.5;pointer-events:none;' ?>">
                <div class="field-suffix field-suffix--narrow">
                    <input type="number" name="documents_max_mb" min="1" max="10"
                           value="<?= htmlspecialchars((string) $val('documents_max_mb')) ?>"
                           placeholder="10">
                    <span class="suffix">Mo max / fichier</span>
                </div>
                <p class="fsection-hint" style="margin:.7rem 0 .3rem;">Formats autorisés :</p>
                <div style="display:flex;gap:1.2rem;flex-wrap:wrap;">
                    <?php $fmtLabels = ['pdf' => 'PDF', 'md' => 'Markdown', 'txt' => 'TXT']; ?>
                    <?php foreach ($availableFormats as $fmt): ?>
                        <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                            <input type="checkbox" name="documents_types[]" value="<?= htmlspecialchars($fmt) ?>"
                                   <?= in_array($fmt, $selectedDocTypes, true) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($fmtLabels[$fmt] ?? strtoupper($fmt)) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <script>
                (function () {
                    var cb = document.getElementById('f-docs-enabled');
                    var opt = document.getElementById('docs-options');
                    if (cb && opt) cb.addEventListener('change', function () {
                        opt.style.opacity = cb.checked ? '' : '.5';
                        opt.style.pointerEvents = cb.checked ? '' : 'none';
                    });
                })();
            </script>
        </section>

        <div class="form-actions">
            <button type="submit" class="btn primary">
                <?= $isEdit ? 'Enregistrer' : 'Créer la session' ?>
            </button>
            <span class="grow"></span>
            <a href="/sessions" class="btn ghost">Annuler</a>
        </div>
    </div>

    <aside class="session-aside">
        <?php if ($previewCodeFormatted !== ''): ?>
        <div class="access-card">
            <div class="access-card-label">Code d'accès</div>
            <div class="access-code-display" id="access-code-display">
                <?= htmlspecialchars($previewCodeFormatted) ?>
            </div>
            <div class="access-card-actions">
                <button type="button" class="btn bordered"
                        data-copy="<?= htmlspecialchars($previewCodeFormatted) ?>" data-copy-feedback="text">
                    <?= icon('copy', '', 11) ?> Copier
                </button>
                <button type="button" class="btn bordered" id="btn-fullscreen-code">
                    <?= icon('eye', '', 11) ?> Plein écran
                </button>
            </div>
        </div>
        <?php endif; ?>

        <div>
            <div class="fsection-head">
                <span class="fsection-label">Aperçu étudiant</span>
                <span class="fsection-rule"></span>
            </div>
            <div class="preview-card">
                <div class="preview-card-header">
                    <span class="preview-card-tag" id="preview-tag"><?= htmlspecialchars(strtolower($currentType->value)) ?> / <?= htmlspecialchars($currentType->label()) ?></span>
                    <?php if ($previewCodeFormatted !== ''): ?>
                        <span class="preview-card-tag">· <?= htmlspecialchars($previewCodeFormatted) ?></span>
                    <?php endif; ?>
                </div>
                <div class="preview-card-name" id="preview-name">— libellé —</div>
                <div class="preview-card-meta">
                    <span id="preview-author"><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></span>
                    · <span id="preview-duration">90</span> min
                </div>
                <div class="preview-models" id="preview-models"></div>
                <div class="preview-prompt" id="preview-prompt" hidden>
                    <span class="preview-prompt-marker"># pré-prompt visible</span><br>
                    <span id="preview-prompt-text"></span>
                </div>
            </div>
        </div>

        <div>
            <div class="fsection-head">
                <span class="fsection-label">Avant de créer</span>
                <span class="fsection-rule"></span>
            </div>
            <ul class="preflight-list">
                <li>
                    <span class="preflight-marker ok"><?= icon('check', '', 12) ?></span>
                    <span><?= count($models) ?> modèle(s) disponible(s)</span>
                </li>
                <li>
                    <span class="preflight-marker <?= $resources === [] ? 'warn' : 'ok' ?>"><?= $resources === [] ? icon('alert-triangle', '', 12) : icon('check', '', 12) ?></span>
                    <span><?= count($resources) ?> ressource(s) disponible(s)</span>
                </li>

                <?php if (!$isEdit): ?>
                    <li id="preflight-resource">
                        <span class="preflight-marker warn"><?= icon('alert-triangle', '', 12) ?></span>
                        <span id="preflight-resource-text">Sélectionnez une ressource</span>
                    </li>
                <?php endif; ?>
                <li id="preflight-selection">
                    <span class="preflight-marker warn"><?= icon('alert-triangle', '', 12) ?></span>
                    <span id="preflight-selection-text">Sélectionnez au moins un modèle</span>
                </li>
            </ul>
        </div>
    </aside>
</form>

<script>
    window.__IAMU_SESSION_FORM__ = {
        code: <?= json_encode($previewCodeFormatted) ?>
    };
</script>
<?php
$jsPath = __DIR__ . '/../../../../public/assets/js/session-create.js';
$jsVer  = is_file($jsPath) ? filemtime($jsPath) : 0;
?>
<script src="/assets/js/session-create.js?v=<?= $jsVer ?>" defer></script>

