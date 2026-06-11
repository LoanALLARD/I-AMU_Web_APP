<?php
$pendingResearchers = $pendingResearchers ?? [];
$pendingSpecialisations = $pendingSpecialisations ?? [];
$habilitatedTeachers = $habilitatedTeachers ?? [];
$revokedTeachers = $revokedTeachers ?? [];
$researchers = $researchers ?? [];
$revokedResearchers = $revokedResearchers ?? [];
$activeNav = 'models';
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
<div class="page-body">
    <?php $this->renderPartial('partials/department_admin/_header', [
        'user' => $user, 'department' => $department ?? null, 'activeNav' => $activeNav,
    ]); ?>

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
                                <option value="">-- Sélectionner --</option>
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
                        <div class="form-section-box" id="resourceScopeBox">
                            <div class="form-group mb-0">
                                <label for="resource_id" class="form-label">Associer à une ressource *</label>
                                <select class="form-select" id="resource_id" name="resource_id" required>
                                    <option value="">-- Choisir la ressource propriétaire --</option>
                                    <?php if (!empty($resources) && is_array($resources)): ?>
                                        <?php foreach($resources as $res): ?>
                                            <option value="<?= htmlspecialchars((string)$res['id']) ?>">
                                                <?= htmlspecialchars($res['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <div id="resource-error-message" class="text-danger d-none">
                                    Veuillez sélectionner une ressource.
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($isAdmin) : ?>
                        <div class="form-group">
                            <label class="form-label">Partager avec tous les départements ?</label>
                            <div class="radio-group-inline">
                                <div class="radio-option">
                                    <input type="radio" id="share_yes" name="is_shareable" value="1" checked>
                                    <label for="share_yes">Oui</label>
                                </div>
                                <div class="radio-option">
                                    <input type="radio" id="share_no" name="is_shareable" value="0">
                                    <label for="share_no">Non</label>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <a href="/chat" class="btn-admin btn-secondary">Annuler</a>
                        <button type="submit" class="btn-admin btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="admin-card models-panel-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Modèles configurés</h3>
            </div>
            <div class="admin-card-body panel-body-scroll">
                <?php if (!empty($models) && is_array($models)): ?>
                    <div class="models-list">
                        <?php foreach ($models as $model) : ?>
                            <div class="model-row">
                                <div class="model-meta">
                                    <div class="model-name-row">
                                        <strong class="model-name"><?= htmlspecialchars((string)$model['name']) ?></strong>
                                        <span class="model-size-badge"><?= htmlspecialchars((string)($model['size'] ?: 'N/A')) ?></span>
                                    </div>
                                    <div class="model-sub-info">
                                        <span class="mono-text"><?= htmlspecialchars((string)$model['provider']) ?></span>
                                        <span class="sep">•</span>
                                        <span class="mono-text"><?= htmlspecialchars((string)$model['adapter']) ?></span>
                                    </div>
                                    <div class="model-url mono-text" title="<?= htmlspecialchars((string)$model['api_url']) ?>">
                                        <?= htmlspecialchars((string)$model['api_url']) ?>
                                    </div>
                                </div>

                                <div class="model-badges">
                                    <?php if ($model['is_shareable']): ?>
                                        <span class="model-badge badge-shared" title="Partagé">Partagé</span>
                                    <?php else: ?>
                                        <span class="model-badge badge-private" title="Privé">Privé</span>
                                    <?php endif; ?>

                                    <?php if ($model['is_active']): ?>
                                        <span class="model-badge badge-active">Actif</span>
                                    <?php else: ?>
                                        <span class="model-badge badge-inactive">Inactif</span>
                                    <?php endif; ?>
                                </div>

                                <div class="model-actions">
                                    <?php if ($model['is_active']): ?>
                                        <form action="/department-admin/models/archive" method="POST" class="action-model-form" onsubmit="return confirm('Êtes-vous sûr de vouloir archiver ce modèle ?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars((string)$model['id']) ?>">
                                            
                                            <button type="submit" class="btn-action-model btn-archive" title="Archiver ce modèle">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M3 6h18"></path>
                                                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form action="/department-admin/models/reactivate" method="POST" class="action-model-form" onsubmit="return confirm('Voulez-vous réactiver ce modèle ?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars((string)$model['id']) ?>">
                                            
                                            <button type="submit" class="btn-action-model btn-reactivate" title="Réactiver ce modèle">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="23 4 23 10 17 10"></polyline>
                                                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="models-empty">
                        <p>Aucun modèle d'IA n'est configuré pour le moment.</p>
                    </div>
                <?php endif; ?>
            </div>
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
        setTimeout(dismiss, 5000);
    });
});
</script>