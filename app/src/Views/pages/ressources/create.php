<?php
/**
 * Create / Edit resource form.
 *
 * @var string                   $mode      'create' | 'edit'
 * @var array<string,mixed>|null $resource  Existing row in edit mode
 * @var array<string,mixed>      $oldInput  Re-fill values on validation error
 */

$isEdit = $mode === 'edit';
$action = $isEdit
    ? '/ressources/' . (int) $resource['id'] . '/update'
    : '/ressources/store';

$val = static function (string $key, mixed $default = '') use ($resource, $oldInput, $isEdit): string {
    if (array_key_exists($key, $oldInput)) {
        return is_scalar($oldInput[$key]) ? (string) $oldInput[$key] : (string) $default;
    }
    if (!$isEdit || $resource === null) {
        return (string) $default;
    }
    return (string) ($resource[$key] ?? $default);
};

$states = [
    'DRAFT'     => 'Brouillon',
    'PUBLISHED' => 'Publiée',
    'ARCHIVED'  => 'Archivée',
];
$currentState = $val('state', 'DRAFT');
$deptTeachers = $deptTeachers ?? [];
?>

<div class="page-header">
    <div class="page-header-row">
        <h1><?= $isEdit ? 'Modifier la ressource' : 'Nouvelle ressource' ?></h1>
        <span class="mono page-header-hint">
            <?= $isEdit ? 'édition' : 'création' ?>
        </span>
    </div>
    <p class="page-sub">Une ressource représente un cours. Elle sera sélectionnable lors de la création d'une session.</p>
</div>

<form method="POST" action="<?= htmlspecialchars($action) ?>"
      class="session-form-grid" id="resource-form"
      style="padding: 24px;">
    <?= csrf_field() ?>

    <div class="session-form-main">

        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Code</span>
                <span class="fsection-rule"></span>
            </div>
            <p class="fsection-hint">Identifiant court du cours, par exemple <span style="font-family:var(--font-mono)">INF302</span>. 50 caractères maximum.</p>
            <input type="text" name="code" id="f-code"
                   value="<?= htmlspecialchars($val('code')) ?>"
                   placeholder="ex. INF302"
                   required maxlength="50"
                   autocomplete="off">
        </section>

        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Nom</span>
                <span class="fsection-rule"></span>
            </div>
            <p class="fsection-hint">Intitulé complet du cours. 50 caractères maximum.</p>
            <input type="text" name="name" id="f-name"
                   value="<?= htmlspecialchars($val('name')) ?>"
                   placeholder="ex. Bases de données"
                   required maxlength="50">
        </section>

        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Enseignants</span>
                <span class="fsection-rule"></span>
            </div>
            <p class="fsection-hint">Enseignants responsables du cours.</p>
            <div class="dropdown">
                <button type="button" id="teachers-btn" class="dropdown-btn">
                    Sélectionner les enseignants
                </button>

                <div id="teachers-content" class="dropdown-content hidden">
                    <?php
                    $checkedIds = [];
                    if (!empty($oldInput['teachers']) && is_array($oldInput['teachers'])) {
                        $checkedIds = array_map('intval', $oldInput['teachers']);
                    } else {
                        $checkedIds = $assignedIds ?? [];
                    }
                    ?>
                    <?php foreach ($deptTeachers as $teacher): ?>
                        <label class="dropdown-item">
                            <input
                                type="checkbox"
                                name="teachers[]"
                                value="<?= (int) $teacher['id'] ?>"
                                <?= in_array((int) $teacher['id'], $checkedIds, true) ? 'checked' : '' ?>
                            >
                            <span>
                                <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Description</span>
                <span class="fsection-rule"></span>
            </div>
            <p class="fsection-hint">Optionnel. Brève description visible dans la liste de vos ressources.</p>
            <textarea name="description"
                      id="f-description"
                      placeholder="(optionnel)"><?= htmlspecialchars($val('description')) ?></textarea>
        </section>

        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">Semestre</span>
                <span class="fsection-rule"></span>
            </div>
            <p class="fsection-hint">Optionnel. Par exemple <span style="font-family:var(--font-mono)">S3</span> ou <span style="font-family:var(--font-mono)">S4</span>. 10 caractères maximum.</p>
            <input type="text" name="semester" id="f-semester"
                   value="<?= htmlspecialchars($val('semester')) ?>"
                   placeholder="ex. S3"
                   maxlength="10"
                   style="max-width: 160px">
        </section>

        <?php if ($isEdit): ?>
        <section class="fsection">
            <div class="fsection-head">
                <span class="fsection-label">État</span>
                <span class="fsection-rule"></span>
            </div>
            <select name="state" id="f-state" style="max-width:240px">
                <?php foreach ($states as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>"
                        <?= $currentState === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </section>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn primary">
                <?= $isEdit ? 'Enregistrer' : 'Créer la ressource' ?>
            </button>
            <span class="grow"></span>
            <a href="/ressources" class="btn ghost">Annuler</a>
        </div>

    </div>

    <!-- Aside: aide contextuelle -->
    <aside class="session-aside">
        <div>
            <div class="fsection-head">
                <span class="fsection-label">À savoir</span>
                <span class="fsection-rule"></span>
            </div>
            <ul class="preflight-list">
                <li>
                    <span class="preflight-marker ok"><?= icon('check', '', 12) ?></span>
                    <span>Le code doit être unique dans votre département.</span>
                </li>
                <li>
                    <span class="preflight-marker ok"><?= icon('check', '', 12) ?></span>
                    <span>Une session ne peut être créée que si une ressource lui est rattachée.</span>
                </li>
                <li>
                    <span class="preflight-marker ok"><?= icon('check', '', 12) ?></span>
                    <span>Une ressource avec des sessions actives ne peut pas être supprimée.</span>
                </li>
                <?php if (!$isEdit): ?>
                <li>
                    <span class="preflight-marker ok"><?= icon('check', '', 12) ?></span>
                    <span>L'état sera <strong>Brouillon</strong> à la création. Passez-la en <strong>Publiée</strong> pour la rendre utilisable.</span>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </aside>

</form>

<script>
const btn = document.getElementById('teachers-btn');
const content = document.getElementById('teachers-content');
const checkboxes = content.querySelectorAll('input[type="checkbox"]');

btn.addEventListener('click', () => {
    content.classList.toggle('hidden');
    btn.classList.toggle('open');
});

function updateButtonLabel() {
    const selected = [...checkboxes]
        .filter(cb => cb.checked)
        .map(cb => cb.closest('.dropdown-item').querySelector('span').textContent.trim());

    if (selected.length === 0) {
        btn.textContent = 'Sélectionner les enseignants';
    }
    else if (selected.length <= 2) {
        btn.textContent = selected.join(', ');
    }
    else {
        btn.textContent = `${selected.length} enseignants sélectionnés`;
    }
}

checkboxes.forEach(cb => {
    cb.addEventListener('change', updateButtonLabel);
});

updateButtonLabel();
</script>