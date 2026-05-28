<h1>Inscription</h1>

<?php if (!empty($_SESSION['_flash'])): ?>
    <?php foreach ($_SESSION['_flash'] as $flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endforeach; ?>
    <?php unset($_SESSION['_flash']); ?>
<?php endif; ?>

<form method="POST" action="/register" style="max-width: 400px;">

    <div class="form-group">
        <label for="first_name">Prenom</label>
        <input type="text" id="first_name" name="first_name"
               value="<?= htmlspecialchars($data['first_name'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="last_name">Nom</label>
        <input type="text" id="last_name" name="last_name"
               value="<?= htmlspecialchars($data['last_name'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label for="email">Adresse email universitaire</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($data['email'] ?? '') ?>"
               placeholder="prenom.nom@etu.univ-amu.fr" required>
        <small>Domaines : @etu.univ-amu.fr, @univ-amu.fr </small>
    </div>

    <div class="form-group">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password"
               placeholder="Minimum 8 caracteres" required minlength="8">
    </div>

    <div class="form-group">
        <label for="password_confirm">Confirmer le mot de passe</label>
        <input type="password" id="password_confirm" name="password_confirm"
               placeholder="Repetez votre mot de passe" required>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="rgpd_consent" value="1" required>
            J'accepte le traitement de mes donnees personnel.
        </label>
    </div>

    <button type="submit">S'inscrire</button>
</form>

<p><a href="/login">Se connecter</a></p>
