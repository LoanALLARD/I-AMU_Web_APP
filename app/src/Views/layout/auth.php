<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I-AMU</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Nunito+Sans:wght@300;400;600&display=swap" rel="stylesheet">
    <?php
    $cssDir = dirname(__DIR__, 3) . '/public/assets/css';
    $v = static fn(string $f): string => '?v=' . (@filemtime("$cssDir/$f") ?: 0);
    ?>
    <link rel="stylesheet" href="/assets/css/style.css<?= $v('style.css') ?>">
    <link rel="stylesheet" href="/assets/css/auth.css<?= $v('auth.css') ?>">
    <link rel="stylesheet" href="/assets/css/gdpr.css<?= $v('gdpr.css') ?>">
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
</head>
<body class="auth-body">
    <main>
        <?= $content ?>
    </main>
</body>
</html>