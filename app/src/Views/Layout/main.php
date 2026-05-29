<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I-AMU</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Nunito+Sans:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/app/public/assets/css/style.css">
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