<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'I-AMU') ?> - I-AMU</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.0/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
        // Applique le thème avant le rendu pour éviter le flash de thème clair
        (function() {
            if (localStorage.getItem('iamu-theme') === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>
    <?php if (!isset($_SESSION['user_id'])): ?>
    <!-- Toggle thème flottant pour les pages sans navbar -->
    <button class="theme-toggle theme-toggle-floating" id="theme-toggle" title="Changer de thème" aria-label="Changer de thème"><?= icon('moon') ?></button>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_id'])):
        // Liens de navigation calculés une fois (réutilisés topbar desktop + bottom nav mobile)
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        $isTeacher    = in_array('teacher', $_SESSION['roles'] ?? []);
        $isAdmin      = in_array('admin', $_SESSION['roles'] ?? []);
        $isResearcher = in_array('researcher', $_SESSION['roles'] ?? []);
        $userInitials = strtoupper(
            substr($_SESSION['user_first_name'] ?? 'U', 0, 1)
          . substr($_SESSION['user_last_name'] ?? '', 0, 1)
        );

        $navItems = [['/chat', 'Chat', 'message-circle']];
        if ($isTeacher || $isAdmin) $navItems[] = ['/sessions', 'Sessions', 'graduation-cap'];
        if ($isResearcher)          $navItems[] = ['/export',   'Export',   'bar-chart-2'];
        if ($isAdmin)               $navItems[] = ['/admin',    'Admin',    'settings'];
    ?>
    <nav class="navbar">
        <div class="navbar-brand">
            <a href="/chat" class="brand-link">
                <img src="/assets/img/logo.png" alt="I-AMU" class="navbar-logo">
            </a>
        </div>
        <div class="navbar-menu">
            <!-- Liens : visibles desktop, masqués mobile (remplacés par bottom nav) -->
            <div class="navbar-links">
                <?php foreach ($navItems as [$href, $label, $_icon]):
                    $active = $currentUri === $href || str_starts_with($currentUri, $href . '/');
                ?>
                    <a href="<?= $href ?>" class="nav-link <?= $active ? 'active' : '' ?>">
                        <?= htmlspecialchars($label === 'Admin' ? 'Administration' : $label) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Indicateur statut Ollama : compact sur mobile (juste le dot) -->
            <div class="ollama-status" id="ollama-status" title="Statut du serveur IA">
                <span class="status-dot" id="ollama-dot"></span>
                <span class="ollama-status-text" id="ollama-status-text">Vérification…</span>
            </div>

            <!-- Toggle thème sombre -->
            <button class="theme-toggle" id="theme-toggle" title="Changer de thème" aria-label="Changer de thème"><?= icon('moon') ?></button>

            <!-- Menu utilisateur déroulant -->
            <div class="user-menu" id="user-menu">
                <button class="user-menu-trigger" id="user-menu-trigger" aria-label="Menu utilisateur">
                    <span class="user-avatar"><?= htmlspecialchars($userInitials) ?></span>
                    <span class="user-menu-name"><?= htmlspecialchars($_SESSION['user_first_name'] . ' ' . $_SESSION['user_last_name']) ?></span>
                    <span class="user-menu-caret">▾</span>
                </button>
                <div class="user-dropdown" id="user-dropdown">
                    <div class="dropdown-header">
                        <div class="dropdown-name"><?= htmlspecialchars($_SESSION['user_first_name'] . ' ' . $_SESSION['user_last_name']) ?></div>
                        <div class="dropdown-email"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
                        <div class="dropdown-roles">
                            <?php foreach ($_SESSION['roles'] ?? [] as $role): ?>
                                <span class="dropdown-role-badge"><?= htmlspecialchars($role) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <a href="/account" class="dropdown-item">Mon compte</a>
                    <a href="/logout" class="dropdown-item dropdown-item-danger">Déconnexion</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ═══ Bottom nav mobile : visible uniquement < 768px ═══════════ -->
    <nav class="mobile-bottom-nav" aria-label="Navigation principale">
        <?php foreach ($navItems as [$href, $label, $iconName]):
            $active = $currentUri === $href || str_starts_with($currentUri, $href . '/');
        ?>
            <a href="<?= $href ?>" class="bottom-nav-item <?= $active ? 'active' : '' ?>">
                <?= icon($iconName) ?>
                <span class="bottom-nav-label"><?= htmlspecialchars($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash-messages">
            <?php foreach ($_SESSION['flash'] as $type => $message): ?>
                <div class="flash flash-<?= $type ?>"><?= htmlspecialchars($message) ?></div>
            <?php endforeach; ?>
            <?php unset($_SESSION['flash']); ?>
        </div>
    <?php endif; ?>

    <main class="main-content">
        <?= $content ?>
    </main>

    <script src="/assets/js/app.js"></script>
    <script>
        // Menu utilisateur déroulant
        (function() {
            const trigger = document.getElementById('user-menu-trigger');
            const dropdown = document.getElementById('user-dropdown');
            if (trigger && dropdown) {
                trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    dropdown.classList.toggle('open');
                });
                document.addEventListener('click', () => dropdown.classList.remove('open'));
            }
        })();

        // Toggle thème sombre
        (function() {
            const toggle = document.getElementById('theme-toggle');
            if (!toggle) return;
            const root = document.documentElement;

            // SVG sun & moon (Lucide, alignés sur app/helpers/icons.php)
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

        // Indicateur de statut Ollama (vérifié au chargement + toutes les 30s)
        (function() {
            const dot = document.getElementById('ollama-dot');
            const text = document.getElementById('ollama-status-text');
            if (!dot || !text) return;

            async function checkOllama() {
                try {
                    const res = await fetch('/chat/ollama/status');
                    const data = await res.json();
                    if (data.online) {
                        dot.className = 'status-dot online';
                        text.textContent = 'IA en ligne';
                    } else {
                        dot.className = 'status-dot offline';
                        text.textContent = 'IA hors ligne';
                    }
                } catch (e) {
                    dot.className = 'status-dot offline';
                    text.textContent = 'IA hors ligne';
                }
            }
            checkOllama();
            setInterval(checkOllama, 30000);
        })();
    </script>
</body>
</html>
