<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titrePage ?? 'Administration') ?> — I-AMU</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Nunito+Sans:wght@300;400;600&display=swap" rel="stylesheet">
<?php
    $cssDir = __DIR__ . '/../../../public/assets/css/';
    $cssVer = static fn (string $f): int => is_file($cssDir . $f) ? filemtime($cssDir . $f) : 0;
?>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= $cssVer('style.css') ?>">
    <link rel="stylesheet" href="/assets/css/auth.css?v=<?= $cssVer('auth.css') ?>">
    <link rel="stylesheet" href="/assets/css/superadmin.css?v=<?= $cssVer('superadmin.css') ?>">
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
</head>
<body class="auth-body superadmin-body<?= !empty($bodyClass) ? ' ' . htmlspecialchars($bodyClass) : '' ?>">
    <main>
        <?= $content ?>
    </main>
</body>
</html>
