<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <a href="/"><img src="/assets/img/logo.png" alt="I-AMU" class="auth-logo-img"></a>
            <p class="auth-subtitle">Créer un compte</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/register" class="auth-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">Prénom</label>
                    <input type="text" id="first_name" name="first_name" 
                           value="<?= htmlspecialchars($data['first_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Nom</label>
                    <input type="text" id="last_name" name="last_name"
                           value="<?= htmlspecialchars($data['last_name'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Adresse email universitaire</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($data['email'] ?? '') ?>"
                       placeholder="prenom.nom@etu.univ-amu.fr" required>
                <small class="form-hint">Domaines autorisés : @etu.univ-amu.fr (étudiants), @univ-amu.fr (enseignants)</small>
            </div>

            <div class="form-group">
                <label for="student_number">Numéro étudiant <small>(étudiants uniquement)</small></label>
                <input type="text" id="student_number" name="student_number"
                       value="<?= htmlspecialchars($data['student_number'] ?? '') ?>"
                       placeholder="Facultatif pour les enseignants">
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

            <div class="form-group form-checkbox">
                <input type="checkbox" id="gdpr_consent" name="gdpr_consent" value="1" required>
                <label for="gdpr_consent">
                    J'accepte le traitement de mes données dans le cadre de la recherche scientifique.
                    Les informations recueillies sont enregistrées pour une recherche visant à analyser 
                    l'usage d'IA générative dans l'enseignement. 
                    <a href="#" class="rgpd-link">En savoir plus</a>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-full">S'inscrire</button>
        </form>

        <div class="auth-footer">
            <p>Déjà un compte ? <a href="/login">Se connecter</a></p>
        </div>
    </div>
</div>
