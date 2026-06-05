<?php
/**
 * Student-facing "join a session" form.
 *
 * Renders inside layout/chat.php — on this non-chat page the sidebar is
 * hidden, so the card sits centered on the soft gray background. The
 * access-code field id (`join-code-input`) is what session-join.js hooks
 * onto to auto-uppercase and insert the dash.
 *
 * @var string $title
 */
?>
<div class="join-wrap">
    <div class="join-card">
        <div class="join-card-icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                <polyline points="10 17 15 12 10 7" />
                <line x1="15" y1="12" x2="3" y2="12" />
            </svg>
        </div>

        <h1>Rejoindre une session</h1>
        <p class="join-card-sub">Entrez le code d'accès à 6 caractères donné par votre enseignant.</p>

        <form method="POST" action="/sessions/join" class="join-form">
            <?= csrf_field() ?>
            <input
                type="text"
                id="join-code-input"
                name="access_code"
                class="join-code-field"
                placeholder="XXX-XXX"
                maxlength="7"
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
                autofocus>
            <button type="submit" class="btn primary join-submit">Rejoindre</button>
        </form>
    </div>
</div>

<script src="/assets/js/session-join.js"></script>
