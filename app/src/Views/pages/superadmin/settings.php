<?php 
$initials = strtoupper(
    substr($user['first_name'] ?? '·', 0, 1)
    . substr($user['last_name']  ?? '·', 0, 1)
);
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
?>

<div class="Settings">

    <?php $activeNav = 'settings';require __DIR__ . '/_header.php'; ?>
    
    <div class="dashboard-content">
        <div class="page-header">
            <div class="page-header-row">
                <h1>Mon profil</h1>
                <span class="mono">compte personnel</span>
            </div>
            <p class="page-sub">Informations de votre compte et gestion de vos données personnelles.</p>
        </div>

        <div class="page-body">
            <div class="profile-flex-centered">

                <div class="dashboard-card">
                    <h2>Identité</h2>
                    <div class="kv-grid">
                        <span class="kv-key">prénom</span>
                        <span class="kv-val"><?= htmlspecialchars($user['first_name'] ?? '—') ?></span>
                        <span class="kv-key">nom</span>
                        <span class="kv-val"><?= htmlspecialchars($user['last_name'] ?? '—') ?></span>
                        <span class="kv-key">email</span>
                        <span class="kv-val mono"><?= htmlspecialchars($user['email'] ?? '—') ?></span>
                    </div>
                    
                    <div class="card-actions">
                        <button type="button" class="btn" id="btn-toggle-edit">
                            <?= icon('edit', '', 14) ?> Modifier mes informations
                        </button>
                    </div>
                </div>


                <div class="dashboard-card edit-form-card" id="edit-form-card">
                    <h2>Modifier mes informations</h2>
                    <form method="POST" action="/super-admin/settings/update" class="edit-profile-form">
                        <?= csrf_field() ?>
                        
                        <div class="form-group">
                            <label for="first_name">Prénom</label>
                            <input type="text" id="first_name" name="first_name" placeholder="<?= htmlspecialchars($user['first_name'] ?? '') ?>" >
                        </div>

                        <div class="form-group">
                            <label for="last_name">Nom</label>
                            <input type="text" id="last_name" name="last_name" placeholder="<?= htmlspecialchars($user['last_name'] ?? '') ?>" >
                        </div>

                        <div class="form-group full-width">
                            <label for="email">Adresse Email</label>
                            <input type="email" id="email" name="email" placeholder="<?= htmlspecialchars($user['email'] ?? '') ?>" >
                        </div>

                        <div class="form-group full-width">
                            <label for="password">Nouveau mot de passe <span class="hint">(laisser vide pour ne pas modifier)</span></label>
                            <input type="password" id="password" name="password">
                        </div>

                        <div class="form-group full-width">
                            <label for="password_confirm">Confirmer le mot de passe</label>
                            <input type="password" id="password_confirm" name="password_confirm">
                        </div>

                        <div class="form-group full-width">
                            <label>
                                <input type="checkbox" id="toggle-password-visibility"> Afficher les mots de passe
                            </label>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn ghost" id="btn-cancel-edit">Annuler</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>

                <aside class="profile-aside-card">
                    <div class="profile-avatar">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                    <div class="profile-display-name"><?= htmlspecialchars($displayName) ?></div>
                    <span class="badge badge-super-admin">Super Administateur</span>
                    <a href="/logout" class="profile-logout">
                        <?= icon('lock', '', 12) ?> Se déconnecter
                    </a>
                </aside>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnToggleEdit = document.getElementById('btn-toggle-edit');
    const btnCancelEdit = document.getElementById('btn-cancel-edit');
    const editFormCard = document.getElementById('edit-form-card');

    btnToggleEdit?.addEventListener('click', (e) => {
        e.preventDefault();
        
        editFormCard.classList.toggle('is-open');
        
        if (editFormCard.classList.contains('is-open')) {
            setTimeout(() => {
                editFormCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 300);
        }
    });

    btnCancelEdit?.addEventListener('click', () => {
        editFormCard.classList.remove('is-open');
    });
});
const togglePasswordVisibility = document.getElementById('toggle-password-visibility');
const passwordInput = document.getElementById('password');
const passwordConfirmInput = document.getElementById('password_confirm');

togglePasswordVisibility?.addEventListener('change', () => {
    const isChecked = togglePasswordVisibility.checked;
    passwordInput.type = isChecked ? 'text' : 'password';
    passwordConfirmInput.type = isChecked ? 'text' : 'password';
});
</script>