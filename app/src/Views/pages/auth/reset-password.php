<?php /** @var string $token @var string|null $error */ ?>
<div class="register-card auth-card-login">

    <div class="card-header">
        <div class="logo-wrap">
            <img src="/assets/img/logo.png" alt="logo_amu">
        </div>
        <h1>Nouveau mot de passe</h1>
        <p>Choisissez un nouveau mot de passe</p>
    </div>

    <div class="accent-bar"></div>

    <div class="card-body">
        <?php require __DIR__ . '/../../partials/_flash.php'; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/reset-password">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password"
                       placeholder="Min. 12 caractères, 1 majuscule, 1 chiffre, 1 caractère spécial"
                       required minlength="12"
                       pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,}"
                       title="Au moins 12 caractères, une majuscule, un chiffre et un caractère spécial.">
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirmer le mot de passe</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>

            <button type="submit" class="btn-submit">
                Réinitialiser mon mot de passe
            </button>
        </form>
    </div>

    <div class="card-footer">
        <a href="/login">Retour à la connexion</a>
    </div>
</div>
