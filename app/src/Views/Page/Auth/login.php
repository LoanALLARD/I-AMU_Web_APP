<h1>Connexion</h1>

<?php if (!empty($_SESSION['_flash'])): ?>
    <?php foreach ($_SESSION['_flash'] as $flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endforeach; ?>
    <?php unset($_SESSION['_flash']); ?>
<?php endif; ?>

<form method="POST" action="/login" style="max-width: 400px;">

    <div class="form-group">
        <label for="email">adresse email universitaire</label>
        <input type="text" id="email" name="email"
               value="<?= htmlspecialchars($email ?? '') ?>"
               placeholder="prenom.nom@etu.univ-amu.fr" required autofocus>
    </div>

    <div class="form-group">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password"
               placeholder="Votre mot de passe" required>
    </div>

    <button type="submit">Se connecter</button>
</form>

<p><a href="/register">S'inscrire</a></p>
