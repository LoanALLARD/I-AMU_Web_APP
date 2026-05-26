<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <a href="/"><img src="/assets/img/logo.png" alt="I-AMU" class="auth-logo-img"></a>
            <p class="auth-subtitle">Plateforme d'IA encadrée pour l'enseignement</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login" class="auth-form">
            <div class="form-group">
                <label for="email">Adresse email universitaire</label>
                <input type="text" id="email" name="email" 
                       value="<?= htmlspecialchars($email ?? '') ?>"
                       placeholder="prenom.nom@etu.univ-amu.fr" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" 
                       placeholder="Votre mot de passe" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Se connecter</button>
        </form>

        <div class="auth-footer">
            <p>Pas encore de compte ? <a href="/register">S'inscrire</a></p>
        </div>
    </div>
</div>
