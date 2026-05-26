<div class="page-container">
    <div class="page-header">
        <h1>Mon compte</h1>
    </div>

    <div class="account-grid">
        <!-- Profil -->
        <div class="form-card">
            <h2>Informations personnelles</h2>
            <form method="POST" action="/account/profile">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">Prénom</label>
                        <input type="text" id="first_name" name="first_name" 
                               value="<?= htmlspecialchars($account['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Nom</label>
                        <input type="text" id="last_name" name="last_name"
                               value="<?= htmlspecialchars($account['last_name']) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" value="<?= htmlspecialchars($account['email']) ?>" disabled>
                    <small class="form-hint">L'email ne peut pas être modifié.</small>
                </div>
                <div class="form-group">
                    <label>Rôles</label>
                    <div class="roles-display">
                        <?php foreach ($roles as $role): ?>
                            <span class="badge badge-free"><?= htmlspecialchars(ucfirst($role)) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Membre depuis</label>
                    <input type="text" value="<?= date('d/m/Y', strtotime($account['created_at'])) ?>" disabled>
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </form>
        </div>

        <!-- Mot de passe -->
        <div class="form-card">
            <h2>Changer le mot de passe</h2>
            <form method="POST" action="/account/password">
                <div class="form-group">
                    <label for="current_password">Mot de passe actuel</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary">Modifier le mot de passe</button>
            </form>
        </div>

        <!-- RGPD -->
        <div class="form-card">
            <h2>Données personnelles (RGPD)</h2>
            <p>Consentement : <?= $account['gdpr_consent'] ? icon('check', 'text-success') . ' Accepté' : icon('x', 'text-error') . ' Refusé' ?>
                <?= $account['gdpr_consent_at'] ? ' le ' . date('d/m/Y à H:i', strtotime($account['gdpr_consent_at'])) : '' ?>
            </p>
            <form method="POST" action="/account/revoke-consent" class="mt-1">
                <button type="submit" class="btn btn-secondary"
                        onclick="return confirm('Retirer votre consentement vous déconnectera de la plateforme. Continuer ?')">
                    Retirer mon consentement
                </button>
            </form>
        </div>

        <!-- Suppression -->
        <div class="form-card form-card-danger">
            <h2>Supprimer mon compte</h2>
            <p>Cette action est irréversible. Toutes vos données seront désactivées.</p>
            <form method="POST" action="/account/delete">
                <div class="form-group">
                    <label for="delete_password">Confirmez avec votre mot de passe</label>
                    <input type="password" id="delete_password" name="delete_password" required>
                </div>
                <button type="submit" class="btn btn-danger"
                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer votre compte ?')">
                    Supprimer définitivement
                </button>
            </form>
        </div>
    </div>
</div>
