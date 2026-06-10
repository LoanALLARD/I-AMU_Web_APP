<?php require __DIR__ . '/../../partials/_flash.php'; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="register-card">

    <div class="card-header">
        <div class="logo-wrap">
            <img src="/assets/img/logo.png" alt="logo_amu">
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
                <label for="email">Adresse e-mail *</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($data['email'] ?? '') ?>"
                       placeholder="prenom.nom@etu.univ-amu.fr" required>
            </div>

            <div class="form-group promo-collapse" id="promo-container">
                <label for="promo_year" class="form-label">Année de promotion *</label>
                <input type="number" id="promo_year" name="promo_year" placeholder="YYYY" min="2022">
            </div>

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
                <input type="password" id="password_confirm" name="password_confirm"
                       placeholder="Répétez votre mot de passe" required>
            </div>

            <div class="gdpr-group promo-collapse" id="research-container">
                <input type="checkbox" id="is_researcher" name="is_researcher" value="1"
                       <?= !empty($data['is_researcher']) ? 'checked' : '' ?>>
                <label for="is_researcher" class="gdpr-label">
                    Je suis un chercheur.
                </label>
            </div>

            <div class="form-row" id="affiliation-fields">
                <div class="form-group">
                    <label for="place_id">Lieu</label>
                    <select id="place_id" name="place_id" required>
                        <option value="">Choisir un lieu...</option>
                        <?php foreach ($places ?? [] as $place): ?>
                            <option value="<?= (int) $place['id'] ?>"
                                <?= (string) ($data['place_id'] ?? '') === (string) $place['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($place['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="department_id">Département</label>
                    <select id="department_id" name="department_id" required disabled
                            data-selected="<?= htmlspecialchars((string) ($data['department_id'] ?? '')) ?>">
                        <option value="">Choisir d'abord un lieu...</option>
                    </select>
                </div>
            </div>

            <div class="gdpr-group">
                <input type="checkbox" id="gdpr_consent" name="gdpr_consent" value="1" required>
                <label for="gdpr_consent" class="gdpr-label">
                    <span id="consent-text-member">
                        J'accepte le traitement de mes données personnelles dans le cadre
                        de la recherche scientifique sur l'usage de l'IA.
                        <a href="/gdpr_consent" target="_blank">En savoir plus</a>
                    </span>
                    <span id="consent-text-researcher" hidden>
                        En tant que chercheur, je m'engage à respecter les conditions d'accès
                        et de traitement des données de la plateforme I-AMU.
                        <a href="/gdpr_consent_researcher" target="_blank">En savoir plus</a>
                    </span>
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

<?php
$jsPath = __DIR__ . '/../../../../public/assets/js/register.js';
$jsVer  = is_file($jsPath) ? filemtime($jsPath) : 0;
?>
<script src="/assets/js/register.js?v=<?= $jsVer ?>" defer></script>