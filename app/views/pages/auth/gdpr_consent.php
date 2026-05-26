<div class="auth-container">
    <div class="auth-card auth-card-wide">
        <div class="auth-header">
            <h1>Consentement au traitement des données</h1>
        </div>

        <div class="rgpd-content">
            <p>Les informations recueillies sur cette plateforme sont enregistrées par <strong>Aix-Marseille Université</strong> 
            pour une recherche visant à <strong>analyser l'usage d'IA générative par les étudiants et suivre les trajectoires d'apprentissage</strong>.</p>

            <p>La base légale du traitement est votre <strong>consentement</strong>.</p>

            <h3>Données collectées</h3>
            <p>Nous collectons vos prompts (questions posées aux modèles d'IA), les réponses générées, 
            les métadonnées associées (horodatage, modèle utilisé, nombre de tokens), ainsi que vos informations 
            d'identification (nom, prénom, email, numéro étudiant).</p>

            <h3>Vos droits</h3>
            <p>Vous pouvez obtenir communication des données vous concernant, les rectifier, 
            demander leur effacement ou exercer votre droit à la limitation du traitement. 
            Vous pouvez retirer à tout moment votre consentement.</p>

            <p>Pour exercer ces droits, contactez le responsable de traitement à l'adresse indiquée 
            dans les mentions légales de l'établissement.</p>

            <p><em>Si vous refusez le traitement de vos données, l'accès à la plateforme sera bloqué.</em></p>
        </div>

        <form method="POST" action="/gdpr/consent" class="rgpd-form">
            <div class="rgpd-buttons">
                <button type="submit" name="consent" value="1" class="btn btn-primary">
                    J'accepte le traitement de mes données
                </button>
                <button type="submit" name="consent" value="0" class="btn btn-secondary">
                    Je refuse
                </button>
            </div>
        </form>
    </div>
</div>
