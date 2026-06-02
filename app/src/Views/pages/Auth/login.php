<?php if (!empty($_SESSION['_flash'])): ?>
    <?php foreach ($_SESSION['_flash'] as $flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endforeach; ?>
    <?php unset($_SESSION['_flash']); ?>
<?php endif; ?>

<div class="register-card">

    <div class="card-header">
        <div class="logo-wrap">
            <img src="/assets/img/logo.png" alt="logo_amu">
        </div>
        <h1>Connexion</h1>
        <p>Accédez à votre espace I-AMU</p>
    </div>

    <div class="accent-bar"></div>

    <div class="card-body">

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/login">

            <div class="form-group">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($email ?? '') ?>"
                       placeholder="prenom.nom@etu.univ-amu.fr" required autofocus>
                <span class="field-hint">Domaines acceptés : @etu.univ-amu.fr, @univ-amu.fr</span>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password"
                       placeholder="Votre mot de passe" required>
            </div>

            <button type="submit" class="btn-submit">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                Se connecter
            </button>

        </form>

    </div>

    <div class="card-footer">
        Pas encore de compte ?&nbsp;<a href="/register">S'inscrire</a>
    </div>

</div>