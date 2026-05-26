<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I-AMU — Plateforme d'IA encadrée pour l'enseignement</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <button class="theme-toggle theme-toggle-floating" id="landing-theme-toggle" title="Changer de thème" aria-label="Changer de thème"><?= icon('moon') ?></button>
    <div class="landing">
        <img src="/assets/img/logo.png" alt="I-AMU" class="landing-logo">
        <h1 class="landing-title">Bienvenue sur I-AMU</h1>
        <p class="landing-subtitle">
            La plateforme d'intelligence artificielle encadrée d'Aix-Marseille Université.
            Accédez à des modèles d'IA dans un cadre pédagogique maîtrisé, pour vos cours,
            vos TP et vos examens.
        </p>
        <div class="landing-actions">
            <a href="/login" class="btn btn-primary btn-lg">Se connecter</a>
            <a href="/register" class="btn btn-outline btn-lg">Créer un compte</a>
        </div>

        <div class="landing-features">
            <div class="landing-feature">
                <div class="landing-feature-icon"><?= icon('message-circle', 'icon-xl') ?></div>
                <div class="landing-feature-title">Mode libre</div>
                <div class="landing-feature-text">Dialoguez avec plusieurs modèles d'IA locaux pour vos travaux.</div>
            </div>
            <div class="landing-feature">
                <div class="landing-feature-icon"><?= icon('graduation-cap', 'icon-xl') ?></div>
                <div class="landing-feature-title">Cours & examens</div>
                <div class="landing-feature-text">Les enseignants encadrent l'usage de l'IA via des sessions dédiées.</div>
            </div>
            <div class="landing-feature">
                <div class="landing-feature-icon"><?= icon('lock', 'icon-xl') ?></div>
                <div class="landing-feature-title">Données protégées</div>
                <div class="landing-feature-text">Conforme RGPD, vos données restent sur l'infrastructure universitaire.</div>
            </div>
        </div>
    </div>

    <script>
        // Applique le thème sauvegardé
        (function() {
            const theme = localStorage.getItem('iamu-theme');
            if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');

            const toggle = document.getElementById('landing-theme-toggle');
            const root = document.documentElement;
            const SVG_MOON = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;
            const SVG_SUN = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>`;
            function refreshIcon() {
                toggle.innerHTML = root.getAttribute('data-theme') === 'dark' ? SVG_SUN : SVG_MOON;
            }
            refreshIcon();
            toggle.addEventListener('click', () => {
                if (root.getAttribute('data-theme') === 'dark') {
                    root.removeAttribute('data-theme');
                    localStorage.setItem('iamu-theme', 'light');
                } else {
                    root.setAttribute('data-theme', 'dark');
                    localStorage.setItem('iamu-theme', 'dark');
                }
                refreshIcon();
            });
        })();
    </script>
</body>
</html>
