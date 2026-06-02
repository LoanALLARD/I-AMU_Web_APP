<aside class="sidebar" id="sidebar">

    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="/assets/img/logo.png" alt="I-AMU">
            <div class="sidebar-logo-text">
                <strong>I-AMU</strong>
                <span><?= htmlspecialchars($user['roles'][0] ?? 'étudiant') ?></span>
            </div>
        </div>
        <button class="sidebar-close" id="sidebarClose" aria-label="Fermer">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <button class="btn-new-chat" id="btnNewChat">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        nouvelle conversation
    </button>

    <div class="sidebar-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="rechercher" id="searchConv">
    </div>

    <div class="sidebar-conversations" id="convList">
        <div class="conv-group">
            <span class="conv-group-label">Aujourd'hui</span>
        </div>
    </div>

    <div class="sidebar-footer">
        <a href="/settings" class="sidebar-footer-link">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.32 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            Paramètres
        </a>
        <a href="/logout" class="sidebar-footer-link sidebar-logout">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Déconnexion
        </a>
    </div>

</aside>

<div class="app-main">

    <header class="app-topbar">
        <button class="topbar-burger" id="burgerBtn" aria-label="Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

        <div class="topbar-breadcrumb">
            <span class="topbar-mode">libre</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            <span class="topbar-conv-name" id="convName">Nouvelle conversation</span>
        </div>

        <div class="topbar-tabs">
            <a href="/chat" class="topbar-tab active">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Chat
            </a>
            <a href="/courses" class="topbar-tab">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                Mes cours
            </a>
            <a href="/history" class="topbar-tab">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Historique
            </a>
        </div>

        <div class="topbar-right">
            <div class="topbar-user-avatar" title="<?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>">
                <?= strtoupper(mb_substr($user['first_name'] ?? '', 0, 1) . mb_substr($user['last_name'] ?? '', 0, 1)) ?>
            </div>
        </div>
    </header>

    <div class="chat-container">
        <div class="chat-area">

            <div class="model-bar">
                <div class="model-tags">
                    <?php $models = $models ?? []; ?>
                    <?php $letters = range('A', 'Z'); $i = 0; ?>
                    <?php foreach ($models as $model): ?>
                        <button class="model-tag<?= $i === 0 ? ' active' : '' ?>"
                                data-model="<?= htmlspecialchars($model['name']) ?>"
                                onclick="selectModel(this)">
                            <span class="model-tag-letter"><?= $letters[$i % 26] ?></span>
                            <span class="model-tag-name"><?= htmlspecialchars($model['name']) ?></span>
                            <span class="model-tag-badge"><?= htmlspecialchars($model['adapter']) ?> · <?= htmlspecialchars($model['provider']) ?></span>
                        </button>
                        <?php $i++; endforeach; ?>

                    <?php if (empty($models)): ?>
                        <span style="font-size:.82rem;color:var(--gray-400);">Aucun modèle disponible</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="messages" id="messages">

                <div class="empty-state" id="emptyState">
                    <div class="empty-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <h2>Bonjour <?= htmlspecialchars($user['first_name'] ?? '') ?> !</h2>
                    <p>Posez une question à l'IA ou sélectionnez un modèle pour commencer.</p>
                    <div class="empty-suggestions">
                        <button class="suggestion-chip" onclick="fillPrompt(this)">Explique-moi les pointeurs en C</button>
                        <button class="suggestion-chip" onclick="fillPrompt(this)">Écris une fonction de tri en Python</button>
                        <button class="suggestion-chip" onclick="fillPrompt(this)">Qu'est-ce que le pattern MVC ?</button>
                    </div>
                </div>

            </div>

            <div class="input-bar">
                <div class="input-wrapper">
                    <textarea
                            id="promptInput"
                            placeholder="Écrivez votre message…"
                            rows="1"
                            autofocus
                    ></textarea>
                    <button class="btn-send" id="btnSend" disabled title="Envoyer">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </div>
                <div class="input-footer">
                    <span class="input-hint">Entrée pour envoyer · Maj+Entrée pour un retour à la ligne</span>
                    <span class="input-counter" id="charCounter">0 car.</span>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
    let currentModel = document.querySelector('.model-tag.active')?.dataset.model || '';

    const input      = document.getElementById('promptInput');
    const sendBtn    = document.getElementById('btnSend');
    const counter    = document.getElementById('charCounter');
    const sidebar    = document.getElementById('sidebar');
    const burgerBtn  = document.getElementById('burgerBtn');
    const sideClose  = document.getElementById('sidebarClose');

    burgerBtn?.addEventListener('click', () => sidebar.classList.add('open'));
    sideClose?.addEventListener('click', () => sidebar.classList.remove('open'));

    function selectModel(btn) {
        document.querySelectorAll('.model-tag').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        currentModel = btn.dataset.model;
    }

    input?.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 200) + 'px';
        sendBtn.disabled = !input.value.trim();
        counter.textContent = input.value.length + ' car.';
    });

    input?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (input.value.trim()) sendMessage();
        }
    });

    sendBtn?.addEventListener('click', () => {
        if (input.value.trim()) sendMessage();
    });

    function fillPrompt(btn) {
        input.value = btn.textContent;
        input.dispatchEvent(new Event('input'));
        input.focus();
    }

    function formatDuration(nanoseconds) {
        const ms = nanoseconds / 1e6;
        if (ms < 1000) return Math.round(ms) + ' ms';
        return (ms / 1000).toFixed(1) + ' s';
    }

    async function sendMessage() {
        const message = input.value.trim();
        if (!message || !currentModel) return;

        const messagesEl = document.getElementById('messages');
        const emptyState = document.getElementById('emptyState');
        if (emptyState) emptyState.style.display = 'none';

        const userMsg = document.createElement('div');
        userMsg.className = 'msg msg-user';
        userMsg.innerHTML = `<div class="msg-content">${escapeHtml(message)}</div>`;
        messagesEl.appendChild(userMsg);

        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;
        counter.textContent = '0 car.';

        const aiMsg = document.createElement('div');
        aiMsg.className = 'msg msg-ai';
        aiMsg.innerHTML = `
            <div class="msg-meta"><span class="msg-model">${escapeHtml(currentModel)}</span></div>
            <div class="msg-content"><span class="typing-indicator"><span></span><span></span><span></span></span></div>
        `;
        messagesEl.appendChild(aiMsg);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        const startTime = performance.now();

        try {
            const res = await fetch('/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    model: currentModel,
                    message: message,
                    context: []
                })
            });
            const data = await res.json();

            if (data.error) {
                aiMsg.querySelector('.msg-content').innerHTML =
                    `<p class="msg-error">${escapeHtml(data.error)}</p>`;
                messagesEl.scrollTop = messagesEl.scrollHeight;
                return;
            }

            const parsed = typeof data.response === 'string'
                ? JSON.parse(data.response)
                : data.response;

            const responseText   = parsed.response || 'Pas de réponse.';
            const totalDuration  = parsed.total_duration || 0;        // nanosecondes
            const inputTokens    = parsed.prompt_eval_count || 0;
            const outputTokens   = parsed.eval_count || 0;
            const totalTokens    = inputTokens + outputTokens;
            const durationStr    = totalDuration > 0 ? formatDuration(totalDuration) : '—';

            aiMsg.querySelector('.msg-content').innerHTML =
                `<p>${escapeHtml(responseText)}</p>`;

            aiMsg.innerHTML += `
                <div class="msg-actions">
                    <button class="msg-action" onclick="copyMsg(this)">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        copier
                    </button>
                    <button class="msg-action">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                        garder
                    </button>
                    <span class="msg-stat" title="Temps de réponse">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        ${durationStr}
                    </span>
                    <span class="msg-stat" title="${inputTokens} entrée + ${outputTokens} sortie">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
                        ${totalTokens} tok
                    </span>
                </div>`;
        } catch (err) {
            aiMsg.querySelector('.msg-content').innerHTML =
                `<p class="msg-error">Erreur de connexion au modèle.</p>`;
        }
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function copyMsg(btn) {
        const text = btn.closest('.msg').querySelector('.msg-content').textContent;
        navigator.clipboard.writeText(text);
        const original = btn.innerHTML;
        btn.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> copié`;
        setTimeout(() => btn.innerHTML = original, 1500);
    }
</script>