<?php if (!empty($_SESSION['_flash'])): ?>
    <?php foreach ($_SESSION['_flash'] as $flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endforeach; ?>
    <?php unset($_SESSION['_flash']); ?>
<?php endif; ?>

<body>

<div class="register-card">

    <div class="card-header">
        <div class="logo-wrap">
            <img src="../../../../public/assets/img/logo.png" alt="logo_amu">
        </div>
        <h1>Créer un compte</h1>
        <p>Réservé aux adresses universitaires AMU</p>
    </div>

    <div class="accent-bar"></div>

    <div class="card-body">

        <form method="POST" action="/register">

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">Prénom</label>
                    <input type="text" id="first_name" name="first_name"
                           value="<?= htmlspecialchars($data['first_name'] ?? '') ?>"
                           placeholder="Thomas" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Nom</label>
                    <input type="text" id="last_name" name="last_name"
                           value="<?= htmlspecialchars($data['last_name'] ?? '') ?>"
                           placeholder="Dupont" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($data['email'] ?? '') ?>"
                       placeholder="prenom.nom@etu.univ-amu.fr" required>
                <span class="field-hint">Domaines acceptés&nbsp;: @etu.univ-amu.fr, @univ-amu.fr</span>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password"
                       placeholder="Minimum 8 caractères" required minlength="8">
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirmer le mot de passe</label>
                <input type="password" id="password_confirm" name="password_confirm"
                       placeholder="Répétez votre mot de passe" required>
            </div>

            <div class="rgpd-group">
                <input type="checkbox" id="rgpd_consent" name="rgpd_consent" value="1" required>
                <label for="rgpd_consent" class="rgpd-label" style="text-transform:none;letter-spacing:0;font-weight:400;">
                    J'accepte le traitement de mes données personnelles dans le cadre
                    de la recherche scientifique sur l'usage de l'IA.
                    <a href="/RGPDConsent" target="_blank">En savoir plus</a>
                </label>
            </div>

            <button type="submit" class="btn-submit">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Créer mon compte
            </button>

        </form>

    </div>

    <div class="card-footer">
        Déjà inscrit&nbsp;?&nbsp;<a href="/login">Se connecter</a>
    </div>

</div>
</body>