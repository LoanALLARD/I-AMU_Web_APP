<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <a href="/"><img src="/assets/img/logo.png" alt="I-AMU" class="auth-logo-img"></a>
            <p class="auth-subtitle">Choisis un nouveau mot de passe</p>
        </div>

        <?php if (empty($valid)): ?>
            <div class="alert alert-error">
                Ce lien de réinitialisation est invalide ou a expiré.
                Refais une demande depuis la page de connexion.
            </div>
            <div class="auth-footer">
                <p><a href="/password/forgot">Demander un nouveau lien</a></p>
                <p><a href="/login">Retour à la connexion</a></p>
            </div>
        <?php else: ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/password/reset" class="auth-form">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe</label>
                    <input type="password" id="new_password" name="new_password"
                           placeholder="8 caractères minimum" required autofocus minlength="8">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmation</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Retape le mot de passe" required minlength="8">
                </div>

                <button type="submit" class="btn btn-primary btn-full">Mettre à jour</button>
            </form>

            <div class="auth-footer">
                <p><a href="/login">Annuler</a></p>
            </div>
        <?php endif; ?>
    </div>
</div>
