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

        <?php if (!empty($error) && empty($deactivated)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($deactivated)): ?>
            <div class="alert alert-error" style="margin-bottom:0;">
                <?= htmlspecialchars($error) ?>
            </div>
            <div style="background:var(--blue-50, #eff6ff);border:1px solid var(--blue-200, #bfdbfe);border-radius:8px;padding:14px 16px;margin-top:12px;margin-bottom:16px;">
                <p style="font-size:13px;color:var(--blue-800, #1e40af);line-height:1.5;margin:0 0 12px;">
                    Vous avez précédemment désactivé votre compte.
                    Souhaitez-vous le réactiver pour retrouver l'accès ?
                </p>
                <form method="POST" action="/reactivate">
                    <?= csrf_field() ?>
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email ?? '') ?>">
                    <input type="hidden" name="password" id="reactivate-password" value="">
                    <button type="submit" class="btn-submit" id="btn-reactivate" style="width:100%;background:var(--blue-600, #2563eb);">
                        Réactiver mon compte
                    </button>
                </form>
            </div>
        <?php endif; ?>


        <form method="POST" action="/login">

            <div class="form-group">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($email ?? '') ?>"
                       placeholder="prenom.nom@exemple.fr" required autofocus>
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
<?php if (!empty($deactivated)): ?>
    <!--
        When the user clicks "Réactiver mon compte", we need to forward
        the password they typed in the login form into the reactivation
        form (which is a separate <form> pointing at /reactivate).
        The password field value is NOT pre-filled by the server for
        security reasons — it is copied client-side at submit time.
    -->
    <script>
        (function() {
            var btnReactivate      = document.getElementById('btn-reactivate');
            var reactivatePassword = document.getElementById('reactivate-password');
            var passwordField      = document.getElementById('password');

            if (!btnReactivate || !reactivatePassword || !passwordField) return;

            btnReactivate.closest('form').addEventListener('submit', function(e) {
                if (passwordField.value === '') {
                    e.preventDefault();
                    passwordField.focus();
                    alert('Veuillez d\'abord saisir votre mot de passe dans le formulaire de connexion.');
                    return;
                }
                reactivatePassword.value = passwordField.value;
            });
        })();
    </script>
<?php endif; ?>
