<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <a href="/"><img src="/assets/img/logo.png" alt="I-AMU" class="auth-logo-img"></a>
            <p class="auth-subtitle">Réinitialisation du mot de passe</p>
        </div>

        <?php if (!empty($submitted)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($genericInfo ?? '') ?></div>

            <?php if (!empty($debugUrl)): ?>
                <div class="alert alert-info" style="word-break: break-all;">
                    <strong>[Mode debug]</strong> Aucun mailer n'étant configuré,
                    voici le lien de réinitialisation :<br>
                    <a href="<?= htmlspecialchars($debugUrl) ?>"><?= htmlspecialchars($debugUrl) ?></a>
                </div>
            <?php endif; ?>

            <div class="auth-footer">
                <p><a href="/login">Retour à la connexion</a></p>
            </div>
        <?php else: ?>
            <p style="margin-bottom: 1rem; color: var(--color-text-muted);">
                Saisis ton adresse email universitaire&nbsp;: si un compte y est
                associé, tu recevras un lien pour définir un nouveau mot de passe.
            </p>

            <form method="POST" action="/password/forgot" class="auth-form">
                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email"
                           placeholder="prenom.nom@etu.univ-amu.fr" required autofocus>
                </div>

                <button type="submit" class="btn btn-primary btn-full">Envoyer le lien</button>
            </form>

            <div class="auth-footer">
                <p><a href="/login">Retour à la connexion</a></p>
            </div>
        <?php endif; ?>
    </div>
</div>
