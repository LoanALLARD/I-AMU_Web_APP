<?php
/**
 * Authenticated app layout — ModernShell + Navbar.
 *
 * Used by every page behind requireAuth() (sessions, chat, future
 * dashboards). Distinct from Layout/main.php which stays minimal for
 * guest pages (login, register, RGPD notice).
 *
 * Receives the rendered view body in `$content`.
 *
 * Optional variables expected from the controller:
 * @var string|null  $title       <title> tag content
 * @var string|null  $breadcrumb  Breadcrumb text shown next to the brand
 * @var string|null  $navSection  "sessions" | "chat" | … — active pill
 * @var string       $content     View output, injected by Core\Controller::render()
 */

$flashes = $_SESSION['_flash'] ?? [];
unset($_SESSION['_flash']);

$loggedIn  = !empty($_SESSION['user_id']);
$roles     = $_SESSION['roles'] ?? [];
$isTeacher = in_array('teacher', $roles, true);
$isStudent = in_array('student', $roles, true);
$displayName = trim(($_SESSION['user_first_name'] ?? '') . ' ' . ($_SESSION['user_last_name'] ?? ''));
$initials = strtoupper(
    substr($_SESSION['user_first_name'] ?? '·', 0, 1)
  . substr($_SESSION['user_last_name']  ?? '·', 0, 1)
);

$navSection = $navSection ?? '';

$navItems = [];
if ($loggedIn) {
    // Chat is open to every authenticated user (spec 03 §1).
    $navItems[] = ['id' => 'chat', 'label' => 'Chat', 'href' => '/chat', 'icon' => 'message-circle'];
}
if ($isTeacher) {
    $navItems[] = ['id' => 'sessions', 'label' => 'Sessions', 'href' => '/sessions', 'icon' => 'graduation-cap'];
}
?>
<?php
// Cache-bust the CSS bundles so browsers fetch the new version as soon
// as we touch a stylesheet — same pattern as the session-create.js
// tags. The query string only changes when a file's mtime changes,
// so HTTP caching keeps working for unchanged builds.
$cssDir = __DIR__ . '/../../../public/assets/css';
$v = static fn (string $name): int =>
    is_file("$cssDir/$name") ? filemtime("$cssDir/$name") : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'I-AMU') ?> — I-AMU</title>
    <link rel="stylesheet" href="/assets/css/tokens.css?v=<?= $v('tokens.css') ?>">
    <link rel="stylesheet" href="/assets/css/themes.css?v=<?= $v('themes.css') ?>">
    <link rel="stylesheet" href="/assets/css/sessions.css?v=<?= $v('sessions.css') ?>">
</head>
<body>
<div class="shell">
    <header class="navbar">
        <div class="navbar-left">
            <span class="brand-mark" aria-hidden="true"></span>
            <div class="brand-text">
                <span class="brand-name">I-AMU</span>
                <span class="brand-role">
                    <?= $isTeacher ? 'enseignant' : ($isStudent ? 'étudiant' : '·') ?>
                </span>
            </div>
            <?php if (!empty($breadcrumb)): ?>
                <span class="breadcrumb">
                    <span class="breadcrumb-sep">/</span>
                    <span class="mono"><?= htmlspecialchars($breadcrumb) ?></span>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($navItems !== []): ?>
            <nav class="nav-pills" aria-label="navigation principale">
                <?php foreach ($navItems as $item): ?>
                    <a href="<?= htmlspecialchars($item['href']) ?>"
                       class="<?= $item['id'] === $navSection ? 'active' : '' ?>">
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php else: ?>
            <span></span>
        <?php endif; ?>

        <div class="navbar-right">
            <?php if ($loggedIn): ?>
                <a href="/profile" class="user-pill" title="Mon profil">
                    <span class="user-pill-name"><?= htmlspecialchars($displayName) ?></span>
                    <span class="user-pill-avatar"><?= htmlspecialchars($initials) ?></span>
                </a>
            <?php else: ?>
                <a href="/login" class="btn bordered sm">Se connecter</a>
            <?php endif; ?>
        </div>
    </header>

    <?php // Mobile bottom navigation — only shown under 720px via CSS.
          // Same nav items as the desktop pills but laid out as a fixed
          // bar with icon + label, in the spirit of native mobile apps. ?>
    <?php if ($navItems !== []): ?>
        <nav class="bottom-nav" aria-label="navigation mobile">
            <?php foreach ($navItems as $item): ?>
                <a href="<?= htmlspecialchars($item['href']) ?>"
                   class="<?= $item['id'] === $navSection ? 'active' : '' ?>">
                    <?= icon($item['icon'], '', 20) ?>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ($flashes !== []): ?>
        <div class="flash-stack" role="status">
            <?php foreach ($flashes as $f): ?>
                <div class="flash <?= htmlspecialchars($f['type'] ?? 'info') ?>">
                    <?= htmlspecialchars($f['message'] ?? '') ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <main class="page">
        <?= $content ?>
    </main>
</div>
</body>
</html>
