<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I-AMU</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Nunito+Sans:wght@300;400;600&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/layoutMain.css">
    <link rel="stylesheet" href="/assets/css/sessions.css">
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
</head>
<?php
// Sessions's AuthService populates these on successful login.
$isAuthenticated = !empty($_SESSION['user_id']);
$roles           = $_SESSION['roles'] ?? [];
$isTeacher       = in_array('teacher', $roles, true);
$displayName     = trim(($_SESSION['user_first_name'] ?? '') . ' ' . ($_SESSION['user_last_name'] ?? ''));
?>
<body>
    <header>
        <nav>
            <a href="<?= $isAuthenticated ? '/chat' : '/login' ?>" class="navbar-brand">
                <img src="/assets/img/logo.png" alt="I-AMU" class="navbar-logo">
            </a>
            <?php if ($isAuthenticated): ?>
                <a href="/chat" class="nav-link">Chat</a>
                <?php if ($isTeacher): ?>
                    <a href="/sessions" class="nav-link">Mes sessions</a>
                <?php endif; ?>
                <span class="nav-spacer"></span>
                <a href="/profile" class="nav-link nav-user">
                    <?= htmlspecialchars($displayName !== '' ? $displayName : 'Mon profil') ?>
                </a>
                <a href="/logout">Déconnexion</a>
            <?php else: ?>
                <span class="nav-spacer"></span>
                <a href="/login" class="nav-link">Connexion</a>
                <a href="/register">Inscription</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <?= $content ?>
    </main>

    <footer>
        <p>&copy; 2026 - Plateforme IAMU</p>
    </footer>
</body>
</html>
