<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Examen') ?> - I-AMU</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/exam.css">
</head>
<body class="exam-mode">
    <!-- Barre d'examen fixe (pas de navigation) -->
    <header class="exam-header">
        <div class="exam-header-left">
            <img src="/assets/img/logo.png" alt="I-AMU" class="exam-logo-img">
            <span class="exam-badge">MODE EXAMEN</span>
        </div>
        <div class="exam-header-center">
            <span class="exam-title"><?= htmlspecialchars($session['name'] ?? '') ?></span>
        </div>
        <div class="exam-header-right">
            <div class="exam-timer" id="exam-timer" data-remaining="<?= $remainingSeconds ?? 0 ?>">
                <span class="timer-icon">⏱</span>
                <span class="timer-value" id="timer-value">--:--:--</span>
            </div>
            <span class="exam-user"><?= htmlspecialchars($_SESSION['user_first_name'] . ' ' . $_SESSION['user_last_name']) ?></span>
        </div>
    </header>

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash-messages">
            <?php foreach ($_SESSION['flash'] as $type => $message): ?>
                <div class="flash flash-<?= $type ?>"><?= htmlspecialchars($message) ?></div>
            <?php endforeach; ?>
            <?php unset($_SESSION['flash']); ?>
        </div>
    <?php endif; ?>

    <main class="exam-content">
        <?= $content ?>
    </main>

    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/exam.js"></script>
</body>
</html>
