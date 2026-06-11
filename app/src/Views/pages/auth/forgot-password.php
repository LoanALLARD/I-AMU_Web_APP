<div class="register-card auth-card-login">

    <div class="card-header">
        <div class="logo-wrap">
            <img src="/assets/img/logo.png" alt="logo_amu">
        </div>
        <h1>Mot de passe oublié</h1>
        <p>Recevez un lien de réinitialisation par email</p>
    </div>

    <div class="accent-bar"></div>

    <div class="card-body">
        <?php require __DIR__ . '/../../partials/_flash.php'; ?>

        <form method="POST" action="/forgot-password">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email"
                       placeholder="prenom.nom@exemple.fr" required autofocus>
            </div>

            <button type="submit" class="btn-submit">
                Envoyer le lien
            </button>
        </form>
    </div>

    <div class="card-footer">
        <a href="/login">Retour à la connexion</a>
    </div>
</div>
