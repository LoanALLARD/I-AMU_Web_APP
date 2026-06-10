<?php
/** @var string $token @var string $email @var string $roleLabel */?>
<main>
    <div class="register-card auth-card-login">
        <div class="card-header">
            <h1>Activer mon compte</h1>
            <p><?= htmlspecialchars($roleLabel ?? 'Administrateur de departement') ?></p>
        </div>
        <div class="accent-bar"></div>
        <div class="card-body">

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/admin-invite/accept">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="form-group">
                    <label>Adresse email</label>
                    <input type="email" value="<?= htmlspecialchars($email) ?>" disabled>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">Prénom</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Nom</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>

                <button type="submit" class="btn-submit">Créer mon compte</button>
            </form>
        </div>
    </div>
</main>