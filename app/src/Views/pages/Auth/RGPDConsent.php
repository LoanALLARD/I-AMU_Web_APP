<div class="rgpd-page">

    <div class="rgpd-header">
        <div>
            <span class="rgpd-label">I-AMU · Protection des données</span>
            <h1 class="rgpd-title">Mentions d'information<br>sur le traitement des données</h1>
        </div>
        <div class="rgpd-meta">
            Conformément au RGPD<br>
            Règlement (UE) 2016/679<br>
            <?= date('d/m/Y') ?>
        </div>
    </div>

    <div class="rgpd-intro">
        Les informations recueillies lors de votre inscription sont enregistrées et traitées par
        <strong>Aix-Marseille Université</strong> dans le cadre d'une recherche scientifique
        visant à étudier l'impact de l'intelligence artificielle générative sur les trajectoires
        d'apprentissage des étudiants.
    </div>

    <div class="rgpd-section">
        <span class="rgpd-section-num">Article 01</span>
        <h2>Responsable du traitement</h2>
        <table class="rgpd-table">
            <tr>
                <td>Organisme</td>
                <td>Aix-Marseille Université (AMU)</td>
            </tr>
            <tr>
                <td>Adresse</td>
                <td>58 Boulevard Charles Livon, 13284 Marseille Cedex 07</td>
            </tr>
            <tr>
                <?php /* MAIL A REVOIR */ ?>
                <td>Contact DPO</td>
                <td><a href="">dpo@univ-amu.fr</a></td>
            </tr>
            <tr>
                <td>Référent projet</td>
                <td>IUT d'Informatique — Département Informatique</td>
            </tr>
        </table>
    </div>

    <div class="rgpd-section">
        <span class="rgpd-section-num">Article 02</span>
        <h2>Finalités et base légale du traitement</h2>
        <p>Vos données sont collectées pour les finalités suivantes :</p>
        <table class="rgpd-table">
            <thead>
            <tr>
                <th>Finalité</th>
                <th>Base légale</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>Gestion du compte utilisateur</td>
                <td>Exécution du contrat (art. 6.1.b)</td>
            </tr>
            <tr>
                <td>Collecte et analyse des prompts étudiants à des fins de recherche</td>
                <td>Consentement explicite (art. 6.1.a)</td>
            </tr>
            <tr>
                <td>Suivi pédagogique par les enseignants</td>
                <td>Intérêt légitime de l'établissement (art. 6.1.f)</td>
            </tr>
            <tr>
                <td>Publication de résultats de recherche scientifique</td>
                <td>Consentement explicite (art. 6.1.a) + art. 89 RGPD</td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="rgpd-section">
        <span class="rgpd-section-num">Article 03</span>
        <h2>Données collectées</h2>
        <table class="rgpd-table">
            <thead>
            <tr>
                <th>Catégorie</th>
                <th>Données concernées</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>Identité</td>
                <td>Nom, prénom, adresse e-mail universitaire</td>
            </tr>
            <tr>
                <td>Authentification</td>
                <td>Mot de passe (chiffré), rôle attribué</td>
            </tr>
            <tr>
                <td>Activité</td>
                <td>Prompts envoyés aux modèles, réponses des LLM, horodatage, sessions rejointes</td>
            </tr>
            <tr>
                <td>Technique</td>
                <td>Adresse IP (lors des examens uniquement), journaux d'accès</td>
            </tr>
            </tbody>
        </table>

        <div class="rgpd-warning" style="margin-top:1rem;">
            <span class="warn-icon">⚠</span>
            <div>
                <strong>Absence d'anonymisation</strong> — La plateforme I-AMU ne procède à aucune
                anonymisation des données. Cette opération relève de la responsabilité des chercheurs
                habilités qui traitent les données en aval, conformément à leurs protocoles de recherche.
            </div>
        </div>
    </div>

    <div class="rgpd-section">
        <span class="rgpd-section-num">Article 04</span>
        <h2>Destinataires des données</h2>
        <table class="rgpd-table">
            <tr>
                <td>Enseignants</td>
                <td>Accès aux prompts de leurs étudiants dans le cadre de leurs cours et examens uniquement</td>
            </tr>
            <tr>
                <td>Chercheurs habilités</td>
                <td>Accès à l'ensemble des données à des fins d'analyse scientifique, après autorisation d'un administrateur</td>
            </tr>
            <tr>
                <td>Administrateurs</td>
                <td>Accès technique à des fins de maintenance et de gestion des rôles</td>
            </tr>
            <tr>
                <td>Tiers</td>
                <td>Aucune communication à des tiers commerciaux</td>
            </tr>
        </table>
    </div>

    <div class="rgpd-section">
        <span class="rgpd-section-num">Article 05</span>
        <h2>Durée de conservation</h2>
        <table class="rgpd-table">
            <tr>
                <td>Données de compte</td>
                <td>Durée du cursus universitaire + 1 an, ou jusqu'à suppression du compte</td>
            </tr>
            <tr>
                <td>Historique des conversations</td>
                <td>Paramétrable par l'utilisateur dans son espace personnel (par défaut : 3 ans)</td>
            </tr>
            <tr>
                <td>Données de recherche</td>
                <td>Jusqu'à la dernière publication scientifique associée au projet (date projetée communiquée par le responsable de traitement)</td>
            </tr>
            <tr>
                <td>Journaux techniques</td>
                <td>6 mois glissants</td>
            </tr>
        </table>
    </div>

    <div class="rgpd-section">
        <span class="rgpd-section-num">Article 06</span>
        <h2>Vos droits</h2>
        <p>Conformément au RGPD, vous disposez des droits suivants sur vos données personnelles :</p>

        <div class="rgpd-rights">
            <div class="rgpd-right-card">
                <strong>Droit d'accès</strong>
                <p>Obtenir une copie des données vous concernant détenues par la plateforme.</p>
            </div>
            <div class="rgpd-right-card">
                <strong>Droit de rectification</strong>
                <p>Faire corriger des données inexactes ou incomplètes vous concernant.</p>
            </div>
            <div class="rgpd-right-card">
                <strong>Droit à l'effacement</strong>
                <p>Demander la suppression de vos données (sous réserve des finalités de recherche).</p>
            </div>
            <div class="rgpd-right-card">
                <strong>Droit à la limitation</strong>
                <p>Geler temporairement l'utilisation de certaines de vos données.</p>
            </div>
            <div class="rgpd-right-card">
                <strong>Droit d'opposition</strong>
                <p>Vous opposer à l'utilisation de vos données pour un traitement précis.</p>
            </div>
            <div class="rgpd-right-card">
                <strong>Retrait du consentement</strong>
                <p>Retirer votre consentement à tout moment depuis les paramètres de votre compte.</p>
            </div>
            <div class="rgpd-right-card">
                <strong>Portabilité</strong>
                <p>Recevoir vos données dans un format structuré et lisible par machine.</p>
            </div>
        </div>

        <div class="rgpd-warning" style="margin-top:1.25rem;">
            <span class="warn-icon">ℹ</span>
            <div>
                Certains de ces droits peuvent faire l'objet de restrictions si leur exercice risque
                d'entraver sérieusement la réalisation des finalités de recherche scientifique,
                conformément à l'article 89 du RGPD. Toute restriction sera motivée et notifiée.
            </div>
        </div>
    </div>

    <div class="rgpd-section">
        <span class="rgpd-section-num">Article 07</span>
        <h2>Exercer vos droits &amp; réclamation</h2>

        <div class="rgpd-contact">
            <strong>Délégué à la Protection des Données (DPO)</strong>
            Aix-Marseille Université<br>
            58 Boulevard Charles Livon — 13284 Marseille Cedex 07<br>
            <a href="mailto:dpo@univ-amu.fr">dpo@univ-amu.fr</a>
        </div>

        <p style="margin-top:1rem; font-size:0.9rem;">
            Si vous estimez, après nous avoir contactés, que vos droits ne sont pas respectés,
            vous pouvez déposer une <strong>réclamation auprès de la CNIL</strong> :
        </p>
        <div class="rgpd-contact">
            <strong>Commission Nationale de l'Informatique et des Libertés</strong>
            3 Place de Fontenoy — TSA 80715 — 75334 Paris Cedex 07<br>
            <a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>
            &nbsp;·&nbsp;
            <a href="https://www.cnil.fr/fr/plaintes" target="_blank" rel="noopener">Déposer une plainte</a>
        </div>
    </div>

    <div class="rgpd-footer">
        <p>I-AMU · IUT Informatique · Aix-Marseille Université<br>
            Document établi conformément au RGPD (UE) 2016/679 et à la loi Informatique et Libertés</p>
        <a href="/register">← Retour à l'inscription</a>
    </div>

</div>