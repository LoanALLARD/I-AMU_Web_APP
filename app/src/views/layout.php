<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $titrePage ?? 'Ma Super App' ?></title>
    <style>
        /* Un peu de CSS pour espacer tes boutons de navigation */
        header nav a { margin-right: 15px; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="/accueil">Accueil</a>
            <a href="/plats">Nos Plats</a>
            
            <?php if (isset($_SESSION['token'])): ?>
                <a href="/commandes">Mes Commandes</a>
                <a href="/cart">Panier</a>
                <a href="/logout">Déconnexion</a>
            <?php else: ?>
                <a href="/login">Connexion</a>
            <?php endif; ?>
        </nav>
    </header>
    <hr>

    <main>
        <?= $content ?>
    </main>

    <hr>
    <footer>
        <p>&copy; 2026 - Plateforme IAMU</p>
    </footer>
</body>
</html>