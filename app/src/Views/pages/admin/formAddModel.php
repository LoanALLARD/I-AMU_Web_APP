<?php
/**
 * @var array $user Le tableau de l'utilisateur connecté (id, email, theme, roles...)
 * @var array $adapters La liste des adaptateurs d'API disponibles
 * @var array $resources La liste des sessions/ressources disponibles
 */

// Résolution du thème
$themePref = match ($user['theme'] ?? null) {
    'DARK'  => 'dark',
    'LIGHT' => 'light',
    default => 'auto',
};

// Extraction des permissions
$role = $user["roles"][0] ?? '';
$is_specialized = !empty($user["isSpecialized"]);
$isTeacherSpecialized = ($role === 'teacher' && $is_specialized);
$isAdmin = ($role === 'department_admin');
?>
<script>
    (function () {
        var p = <?= json_encode($themePref) ?>;
        var dark = p === 'dark' || (p === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        if (dark) document.documentElement.setAttribute('data-theme', 'dark');
        else document.documentElement.removeAttribute('data-theme');
    })();
</script>

<div class="admin-container">

    <?php /* Flash messages are rendered once by the chat layout (partials/_flash.php). */ ?>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Ajouter un nouveau Modèle d'IA</h3>
        </div>
        <div class="admin-card-body">
            <form action="/department-admin/addModel" method="POST" id="addModelForm" novalidate>
                
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="name" class="form-label">Nom du modèle *</label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="ex: llama3.2:1b">
                    </div>
                    <div class="form-group">
                        <label for="size" class="form-label">Taille</label>
                        <input type="text" class="form-control" id="size" name="size" placeholder="ex: 1b">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="provider" class="form-label">Fournisseur *</label>
                        <input type="text" class="form-control" id="provider" name="provider" required placeholder="ex: metaAI, openai">
                    </div>
                    <div class="form-group">
                        <label for="adapter" class="form-label">Adaptateur *</label>
                        <select class="form-select" id="adapter" name="adapter" required>
                            <option value="">-- Sélectionner le type de l'api --</option>
                            <?php if (!empty($adapters) && is_array($adapters)): ?>
                                <?php foreach($adapters as $adapter): ?>
                                    <option value="<?= htmlspecialchars((string)$adapter) ?>">
                                        <?= htmlspecialchars($adapter) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="api_url" class="form-label">URL de l'API *</label>
                    <input type="text" class="form-control" id="api_url" name="api_url" required placeholder="http://localhost:11434/api/generate">
                </div>

                <div class="form-group">
                    <label for="context_window" class="form-label">Fenêtre de contexte *</label>
                    <input type="number" class="form-control" id="context_window" name="context_window" min="1" required placeholder="128000">
                </div>

                <?php if ($isTeacherSpecialized) : ?>
                    <input type="hidden" name="is_shareable" value="0">
                    <div class="form-section-box" id="resourceScopeBox" style="margin-top: 1.5rem; padding: 1.25rem; border-radius: 8px; background: var(--gray-100); border: 1px solid var(--gray-200);">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="resource_id" class="form-label">Associer à une ressource / session spécifique *</label>
                            <select class="form-select" id="resource_id" name="resource_id" required>
                                <option value="">-- Choisir la ressource propriétaire du modèle --</option>
                                <?php if (!empty($resources) && is_array($resources)): ?>
                                    <?php foreach($resources as $res): ?>
                                        <option value="<?= htmlspecialchars((string)$res['id']) ?>">
                                            <?= htmlspecialchars($res['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div id="resource-error-message" class="text-danger d-none" style="color: #dc3545; font-size: 0.8rem; margin-top: 0.4rem;">
                                Veuillez sélectionner une ressource.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($isAdmin) : ?>
                    <div class="form-group">
                        <label class="form-label">Partager le modèle avec tous les départements ?</label>
                        <div class="radio-group-inline">
                            <div class="radio-option">
                                <input type="radio" id="share_yes" name="is_shareable" value="1" checked>
                                <label for="share_yes">Oui, le partager pour aux autres départements</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="share_no" name="is_shareable" value="0">
                                <label for="share_no">Non, ne pas le partager</label>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-actions" style="margin-top: 2rem;">
                    <a href="/chat" class="btn-admin btn-secondary">Annuler</a>
                    <button type="submit" class="btn-admin btn-primary">Enregistrer le modèle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('addModelForm');
    const resourceSelect = document.getElementById('resource_id');
    const resourceError = document.getElementById('resource-error-message');

    // Validation du formulaire
    if (form) {
        form.addEventListener('submit', function (event) {
            let isValid = true;

            if (resourceSelect) {
                if (resourceSelect.value === "") {
                    resourceSelect.classList.add('is-invalid');
                    if (resourceError) resourceError.classList.remove('d-none');
                    isValid = false;
                } else {
                    resourceSelect.classList.remove('is-invalid');
                    if (resourceError) resourceError.classList.add('d-none');
                }
            }

            if (!form.checkValidity() || !isValid) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    }

    if (resourceSelect) {
        resourceSelect.addEventListener('change', function () {
            if (this.value !== "") {
                this.classList.remove('is-invalid');
                if (resourceError) resourceError.classList.add('d-none');
            }
        });
    }

    // Gestion de la fermeture automatique des alertes flash
    document.querySelectorAll('.flash-stack .alert').forEach((el) => {
        const dismiss = () => {
            el.style.transition = 'opacity .3s ease, transform .3s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-5px)';
            setTimeout(() => el.remove(), 300);
        };
        el.addEventListener('click', dismiss);
        setTimeout(dismiss, 5000); // Fait disparaître l'alerte au bout de 5 secondes
    });
});
</script>