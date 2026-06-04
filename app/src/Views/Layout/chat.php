<?php
/**
 * Universal authenticated layout — sidebar + topbar shell.
 *
 * Used by every page behind requireAuth(): /chat, /sessions/*, /profile.
 * Hosts the sidebar (brand + nav + footer) and topbar (burger +
 * contextual breadcrumb + tabs + user avatar). The view's body is
 * injected as the main content area.
 *
 * Variables consumed (all optional, with defaults):
 *   $user       — currentUser() array, drives role gating and avatar
 *   $page       — string flag: 'chat' | 'sessions' | 'profile' | 'other'
 *                 Drives the chat-specific sidebar widgets and active pill
 *   $pageTitle  — breadcrumb text shown in the topbar on non-chat pages
 *   $content    — view output, injected by Core\Controller::render()
 */

$user = $user ?? null;
$page = $page ?? 'other';
$pageTitle = $pageTitle ?? '';
$conversation = $conversation ?? null;
$conversations = $conversations ?? [];
$env = $env ?? null;
$archivedView = $archivedView ?? false;
// "Chat" nav target: stay inside the open session conversation instead of
// dropping back to free chat.
$chatHref = (($env['mode'] ?? '') === 'session' && !empty($conversation['id']))
    ? '/chat/' . (int) $conversation['id']
    : '/chat';
$roles = $user['roles'] ?? [];
$isTeacher = in_array('teacher', $roles, true);
$isStudent = in_array('student', $roles, true);
$isDeptAdmin = in_array('department_admin', $roles, true);
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$initials = strtoupper(
    mb_substr($user['first_name'] ?? '·', 0, 1)
    . mb_substr($user['last_name'] ?? '·', 0, 1)
);
$roleLabel = $isTeacher ? 'enseignant' : ($isStudent ? 'étudiant' : 'compte');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I-AMU<?= $pageTitle !== '' ? ' · ' . htmlspecialchars($pageTitle) : '' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&family=Nunito+Sans:wght@300;400;600&family=JetBrains+Mono:wght@400;500;700&display=swap"
        rel="stylesheet">
    <?php
    // Cache-bust stylesheets via filemtime() so layout fixes don't
    // get hidden behind aggressive browser caching during dev.
    $cssDir = dirname(__DIR__, 3) . '/public/assets/css';
    $v = static fn(string $f): string => '?v=' . (@filemtime("$cssDir/$f") ?: 0);
    ?>
    <link rel="stylesheet" href="/assets/css/style.css<?= $v('style.css') ?>">
    <link rel="stylesheet" href="/assets/css/homeChat.css<?= $v('homeChat.css') ?>">
    <link rel="stylesheet" href="/assets/css/sessions.css<?= $v('sessions.css') ?>">
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
</head>

<body class="app-body page-<?= htmlspecialchars($page) ?>">

    <header class="app-topbar">
        <a href="<?= $isDeptAdmin ? '/admin' : '/chat' ?>" class="topbar-brand" aria-label="Accueil I-AMU">
            <img src="/assets/img/logo.png" alt="">
            <div class="topbar-brand-text">
                <strong>I-AMU</strong>
                <span><?= htmlspecialchars($roleLabel) ?></span>
            </div>
        </a>

        <button class="topbar-burger" id="burgerBtn" aria-label="Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12" />
                <line x1="3" y1="6" x2="21" y2="6" />
                <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
        </button>

        <?php if ($page === 'chat'): ?>
            <?php $isSessionChat = !empty($conversation['sessionId']); ?>
            <div class="topbar-breadcrumb">
                <span class="topbar-mode<?= $isSessionChat ? ' topbar-mode-session' : '' ?>">
                    <?= $isSessionChat ? 'session' : 'libre' ?>
                </span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6" />
                </svg>
                <span class="topbar-conv-name"
                    id="convName"><?= htmlspecialchars($conversation['name'] ?? 'Nouvelle conversation') ?></span>
            </div>
        <?php else: ?>
            <?php /* Other pages already display their own H1 in .page-header,
          so the topbar stays uncluttered: brand on the left, tabs +
          avatar on the right. The empty spacer pushes the right-side
          group via margin-left:auto on .topbar-tabs. */ ?>
            <div class="topbar-breadcrumb"></div>
        <?php endif; ?>

        <div class="topbar-tabs">
            <?php if (!$isDeptAdmin): ?>
            <a href="<?= htmlspecialchars($chatHref) ?>" class="topbar-tab<?= $page === 'chat' ? ' active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                </svg>
                Chat
            </a>
            <?php endif; ?>
            <?php if ($isTeacher): ?>
                <a href="/sessions" class="topbar-tab<?= $page === 'sessions' ? ' active' : '' ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                        <path d="M6 12v5c3 3 9 3 12 0v-5" />
                    </svg>
                    Mes sessions
                </a>
            <?php endif; ?>
        </div>

        <div class="topbar-right">
            <a href="/profile" class="topbar-user"
                title="<?= htmlspecialchars($displayName !== '' ? $displayName : 'Mon profil') ?>">
                <span
                    class="topbar-user-name"><?= htmlspecialchars($displayName !== '' ? $displayName : 'Mon profil') ?></span>
                <span class="topbar-user-avatar"><?= htmlspecialchars($initials) ?></span>
            </a>
        </div>
    </header>

    <div class="app-body-row">

        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <aside class="sidebar" id="sidebar">

            <button class="sidebar-close" id="sidebarClose" aria-label="Fermer">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>

            <?php /* Drawer header brand — mobile only. On desktop the brand
lives in the topbar; inside the drawer it gives the slide-out menu
its own identity above the navigation. */ ?>
            <div class="sidebar-brand">
                <img src="/assets/img/logo.png" alt="">
                <div class="sidebar-brand-text">
                    <strong>I-AMU</strong>
                    <span><?= htmlspecialchars($roleLabel) ?></span>
                </div>
            </div>

            <?php /* Primary navigation — shown inside the drawer on mobile only.
On desktop these live as pills in the topbar (.topbar-tabs), so
.sidebar-nav stays display:none there to avoid duplication. */ ?>
            <nav class="sidebar-nav" aria-label="Navigation principale">
                <?php if (!$isDeptAdmin): ?>
                <a href="<?= htmlspecialchars($chatHref) ?>"
                    class="sidebar-nav-link<?= $page === 'chat' ? ' is-active' : '' ?>">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                    </svg>
                    Chat
                </a>
                <?php endif; ?>
                <?php if ($isTeacher): ?>
                    <a href="/sessions" class="sidebar-nav-link<?= $page === 'sessions' ? ' is-active' : '' ?>">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                            <path d="M6 12v5c3 3 9 3 12 0v-5" />
                        </svg>
                        Mes sessions
                    </a>
                <?php endif; ?>
            </nav>

            <?php if ($page === 'chat'): ?>
                <?php /* Current environment: session (filtered to one session's
               conversations) or free. */ ?>
                <div class="sidebar-env">
                    <span class="sidebar-env-label<?= ($env['mode'] ?? '') === 'session' ? ' is-session' : '' ?>">
                        <?= htmlspecialchars($env['label'] ?? 'Chat libre') ?>
                    </span>
                </div>

                <form method="POST" action="/chat/new" class="new-chat-form">
                    <?= csrf_field() ?>
                    <?php if (!empty($env['sessionId'])): ?>
                        <input type="hidden" name="session_id" value="<?= (int) $env['sessionId'] ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn-new-chat" id="btnNewChat">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                            stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Nouvelle conversation
                    </button>
                </form>

                <?php if (($env['mode'] ?? '') === 'session'): ?>
                    <a href="/chat" class="btn-leave-session"
                        onclick="return confirm('Quitter la session et revenir au chat libre ? Vos conversations de session restent accessibles en la rejoignant à nouveau.');">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <polyline points="16 17 21 12 16 7" />
                            <line x1="21" y1="12" x2="9" y2="12" />
                        </svg>
                        Quitter la session
                    </a>
                <?php elseif ($isStudent): ?>
                    <a href="/sessions/join" class="btn-join-session">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                            <polyline points="10 17 15 12 10 7" />
                            <line x1="15" y1="12" x2="3" y2="12" />
                        </svg>
                        Rejoindre une session
                    </a>
                <?php endif; ?>

                <div class="sidebar-search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input type="text" placeholder="Rechercher" id="searchConv">
                </div>

                <div class="sidebar-conversations" id="convList">
                    <div class="conv-group">
                        <?php /* Scope switch: the "Conversations" header is a
                       dropdown that flips the list between active and
                       archived conversations of the current environment.
                       The scope rides on ?archived=1; the open conversation
                       is unaffected. */ ?>
                        <?php $isSessionEnv = ($env['mode'] ?? 'libre') === 'session'; ?>
                        <?php $scopeBase = !empty($conversation['id']) ? '/chat/' . (int) $conversation['id'] : '/chat'; ?>
                        <?php if ($isSessionEnv): ?>
                            <?php /* Session conversations are driven by the session
                                   lifecycle: no rename, no archive — hence no scope
                                   switch either (a session never has an archive). */ ?>
                            <span class="conv-group-label">Conversations</span>
                        <?php else: ?>
                            <div class="conv-scope">
                                <button type="button" class="conv-scope-toggle" id="convScopeToggle" aria-haspopup="true"
                                    aria-expanded="false">
                                    <span class="conv-group-label"><?= $archivedView ? 'Archivées' : 'Conversations' ?></span>
                                    <svg class="conv-scope-chevron" width="13" height="13" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"
                                        aria-hidden="true">
                                        <polyline points="6 9 12 15 18 9" />
                                    </svg>
                                </button>
                                <div class="conv-scope-menu" id="convScopeMenu" role="menu" hidden>
                                    <a href="<?= htmlspecialchars($scopeBase) ?>"
                                        class="conv-scope-item<?= !$archivedView ? ' is-current' : '' ?>" role="menuitem">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                        </svg>
                                        Conversations
                                    </a>
                                    <a href="<?= htmlspecialchars($scopeBase) ?>?archived=1"
                                        class="conv-scope-item<?= $archivedView ? ' is-current' : '' ?>" role="menuitem">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="2" y="3" width="20" height="5" rx="1" />
                                            <path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8" />
                                            <path d="M10 12h4" />
                                        </svg>
                                        Archivées
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php $activeConvId = $conversation['id'] ?? null; ?>
                        <?php /* Renaming is allowed only in the free environment:
                       session conversations keep their generated name. The
                       list is homogeneous per environment, so one flag covers
                       every row shown. */ ?>
                        <?php $canRename = ($env['mode'] ?? 'libre') !== 'session'; ?>
                        <?php foreach (($conversations ?? []) as $c): ?>
                            <div class="conv-row<?= (int) $c['id'] === (int) $activeConvId ? ' active' : '' ?>">
                                <a href="/chat/<?= (int) $c['id'] ?>" class="conv-item">
                                    <span class="conv-title"><?= htmlspecialchars($c['name']) ?></span>
                                </a>
                                <?php if (!$isSessionEnv): ?>
                                    <div class="conv-actions">
                                        <button type="button" class="conv-menu-btn" aria-haspopup="true" aria-expanded="false"
                                            aria-label="Actions sur la conversation">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                <circle cx="12" cy="5" r="1.7" />
                                                <circle cx="12" cy="12" r="1.7" />
                                                <circle cx="12" cy="19" r="1.7" />
                                            </svg>
                                        </button>
                                        <div class="conv-menu" role="menu" hidden>
                                                <?php if (!$archivedView && $canRename): ?>
                                                <button type="button" class="conv-menu-item" role="menuitem" data-action="rename"
                                                    data-id="<?= (int) $c['id'] ?>"
                                                    data-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 20h9" />
                                                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                                    </svg>
                                                    Renommer
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($archivedView): ?>
                                                <button type="button" class="conv-menu-item" role="menuitem" data-action="unarchive"
                                                    data-id="<?= (int) $c['id'] ?>">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M3 7v13a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7" />
                                                        <rect x="1" y="3" width="22" height="4" rx="1" />
                                                        <path d="M12 17V11" />
                                                        <polyline points="9 13 12 10 15 13" />
                                                    </svg>
                                                    Restaurer
                                                </button>
                                                <?php else: ?>
                                                <button type="button" class="conv-menu-item" role="menuitem" data-action="archive"
                                                    data-id="<?= (int) $c['id'] ?>">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="2" y="3" width="20" height="5" rx="1" />
                                                        <path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8" />
                                                        <path d="M10 12h4" />
                                                    </svg>
                                                    Archiver
                                                </button>
                                                <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($conversations)): ?>
                            <p class="conv-empty">
                                <?= $archivedView ? 'Aucune conversation archivée.' : 'Aucune conversation pour le moment.' ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php /* Shared submit targets for the per-conversation kebab
               menu. JS fills `id` (and `name` for rename) before submitting,
               so we keep a single CSRF-protected form per action instead of
               one per row. `current_id` is the open conversation, used to
               return there after the action. */ ?>
                <form method="POST" action="/chat/rename" id="convRenameForm" hidden>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="name" value="">
                    <input type="hidden" name="current_id" value="<?= (int) ($conversation['id'] ?? 0) ?>">
                </form>
                <form method="POST" action="/chat/archive" id="convArchiveForm" hidden>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="current_id" value="<?= (int) ($conversation['id'] ?? 0) ?>">
                </form>
                <form method="POST" action="/chat/unarchive" id="convUnarchiveForm" hidden>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="">
                </form>
            <?php endif; ?>

            <div class="sidebar-footer">
                <a href="/profile" class="sidebar-footer-link<?= $page === 'profile' ? ' is-active' : '' ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                    Mon profil
                </a>
                <a href="/logout" class="sidebar-footer-link sidebar-logout">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                    Déconnexion
                </a>
            </div>

        </aside>

        <div class="app-main">
            <div class="app-content">
                <?php if (!empty($_SESSION['_flash'])): ?>
                    <div class="flash-stack">
                        <?php foreach ($_SESSION['_flash'] as $flash): ?>
                            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                                <?= htmlspecialchars($flash['message']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php unset($_SESSION['_flash']); ?>
                <?php endif; ?>
                <?= $content ?>
            </div>
        </div>

    </div><!-- /.app-body-row -->

    <script>
        // Universal sidebar drawer for mobile — runs on every page using this layout.
        (function () {
            const sidebar = document.getElementById('sidebar');
            const burgerBtn = document.getElementById('burgerBtn');
            const sideClose = document.getElementById('sidebarClose');
            const backdrop = document.getElementById('sidebarBackdrop');

            function open() {
                sidebar?.classList.add('open');
                backdrop?.classList.add('show');
            }
            function close() {
                sidebar?.classList.remove('open');
                backdrop?.classList.remove('show');
            }

            burgerBtn?.addEventListener('click', open);
            sideClose?.addEventListener('click', close);
            backdrop?.addEventListener('click', close);
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') close();
            });
        })();

        // Flash toasts: click to dismiss, and auto-dismiss after 5s.
        (function () {
            document.querySelectorAll('.flash-stack .alert').forEach((el) => {
                const dismiss = () => {
                    el.style.transition = 'opacity .2s ease, transform .2s ease';
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(-8px)';
                    setTimeout(() => el.remove(), 200);
                };
                el.addEventListener('click', dismiss);
                setTimeout(dismiss, 5000);
            });
        })();

        // Conversation sidebar menus: the scope switch (Conversations /
        // Archivées) and the per-conversation kebab (rename / archive /
        // unarchive). Kebab actions drive shared hidden forms — we just fill
        // the id (and name) and submit.
        (function () {
            const convList = document.getElementById('convList');
            const scopeToggle = document.getElementById('convScopeToggle');
            const scopeMenu = document.getElementById('convScopeMenu');
            if (!convList && !scopeToggle) return;

            const renameForm = document.getElementById('convRenameForm');
            const archiveForm = document.getElementById('convArchiveForm');
            const unarchiveForm = document.getElementById('convUnarchiveForm');

            function closeKebabs(except) {
                convList?.querySelectorAll('.conv-menu').forEach((menu) => {
                    if (menu === except) return;
                    menu.hidden = true;
                    menu.parentElement.querySelector('.conv-menu-btn')?.setAttribute('aria-expanded', 'false');
                });
            }
            function closeScope() {
                if (!scopeMenu) return;
                scopeMenu.hidden = true;
                scopeToggle?.setAttribute('aria-expanded', 'false');
            }
            function closeAll(exceptKebab) {
                closeKebabs(exceptKebab);
                closeScope();
            }

            // Scope dropdown.
            scopeToggle?.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const willOpen = scopeMenu.hidden;
                closeKebabs(null);
                scopeMenu.hidden = !willOpen;
                scopeToggle.setAttribute('aria-expanded', String(willOpen));
            });

            // Kebab menus + actions.
            convList?.addEventListener('click', (e) => {
                const toggle = e.target.closest('.conv-menu-btn');
                if (toggle) {
                    e.preventDefault();
                    e.stopPropagation();
                    const menu = toggle.parentElement.querySelector('.conv-menu');
                    const willOpen = menu.hidden;
                    closeAll(willOpen ? menu : null);
                    menu.hidden = !willOpen;
                    toggle.setAttribute('aria-expanded', String(willOpen));
                    return;
                }

                const item = e.target.closest('.conv-menu-item');
                if (!item) return;
                e.preventDefault();
                closeKebabs(null);

                const id = item.dataset.id;
                const action = item.dataset.action;
                if (action === 'rename' && renameForm) {
                    const next = window.prompt('Nouveau nom de la conversation :', item.dataset.name || '');
                    if (next === null || next.trim() === '') return;
                    renameForm.querySelector('[name="id"]').value = id;
                    renameForm.querySelector('[name="name"]').value = next.trim();
                    renameForm.submit();
                } else if (action === 'archive' && archiveForm) {
                    if (!window.confirm("Archiver cette conversation ? Elle n'apparaîtra plus dans la liste.")) return;
                    archiveForm.querySelector('[name="id"]').value = id;
                    archiveForm.submit();
                } else if (action === 'unarchive' && unarchiveForm) {
                    unarchiveForm.querySelector('[name="id"]').value = id;
                    unarchiveForm.submit();
                }
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.conv-actions') && !e.target.closest('.conv-scope')) closeAll(null);
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeAll(null);
            });
        })();
    </script>

</body>

</html>