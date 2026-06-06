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
<<<<<<< HEAD
    <link rel="stylesheet" href="/assets/css/sessions.css">
    <link rel="stylesheet" href="/assets/css/department_admin.css">
=======
    <link rel="stylesheet" href="/assets/css/components.css">
>>>>>>> 5ffd2f9a34c3219185cf12f14fbbdc6e0e89ae77
    <link rel="stylesheet" href="/assets/css/sessions.css">
    <link rel="stylesheet" href="/assets/css/department_admin.css">    
    <link rel="stylesheet" href="/assets/css/components.css">
    <link rel="stylesheet" href="/assets/css/error.css">
    <link rel="stylesheet" href="/assets/css/rgpd.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
</head>
<?php
// Sessions's AuthService populates these on successful login.
$isAuthenticated = !empty($_SESSION['user_id']);
$roles           = $_SESSION['roles'] ?? [];
$isTeacher       = in_array('teacher', $roles, true);
$isDeptAdmin     = in_array('department_admin', $roles, true);
$displayName     = trim(($_SESSION['user_first_name'] ?? '') . ' ' . ($_SESSION['user_last_name'] ?? ''));

// Stale-session safeguard: a logged-in browser that doesn't carry the
// `roles` key was authenticated before AuthService::resolveRoles
// existed (or by a different AuthService). Force a logout so the next
// login populates the session with proper roles instead of silently
// hiding role-gated nav entries like "Mes sessions".
if ($isAuthenticated && $roles === []) {
    header('Location: /logout');
    exit;
}
?>
<body>
    <header>
        <nav>
            <a href="<?= !$isAuthenticated ? '/login' : ($isDeptAdmin ? '/department-admin' : '/chat') ?>" class="navbar-brand">
                <img src="/assets/img/logo.png" alt="I-AMU" class="navbar-logo">
            </a>
            <?php if ($isAuthenticated): ?>
                <?php if (!$isDeptAdmin): ?>
                    <a href="/chat" class="nav-link">Chat</a>
                <?php endif; ?>
                <?php if ($isTeacher): ?>
                    <a href="/sessions" class="nav-link">Mes sessions</a>
                <?php endif; ?>
                <?php if ($isDeptAdmin): ?>
                    <a href="/department-admin" class="nav-link">Administration</a>
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
