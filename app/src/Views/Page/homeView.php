<div class="chat-area">

    <div class="model-bar">
        <div class="model-tags">
            <button class="model-tag active" data-model="llama3.2:1b">
                <span class="model-tag-letter">A</span>
                <span class="model-tag-name">llama-3.2 · 1b</span>
                <span class="model-tag-badge">local · ollama</span>
            </button>
        </div>
        <button class="btn-compare" id="btnCompare" title="Comparer 2 modèles">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="8" height="18" rx="1"/><rect x="14" y="3" width="8" height="18" rx="1"/></svg>
            Comparer 2 modèles
        </button>
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

<script>
    const input   = document.getElementById('promptInput');
    const sendBtn = document.getElementById('btnSend');
    const counter = document.getElementById('charCounter');

    // Auto-resize textarea
    input?.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 200) + 'px';
        sendBtn.disabled = !input.value.trim();
        counter.textContent = input.value.length + ' car.';
    });

    // Enter to send  Shift+Enter for newline
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

    async function sendMessage() {
        const message = input.value.trim();
        if (!message) return;

        const messagesEl = document.getElementById('messages');
        const emptyState = document.getElementById('emptyState');
        if (emptyState) emptyState.style.display = 'none';

        // Add user message
        const userMsg = document.createElement('div');
        userMsg.className = 'msg msg-user';
        userMsg.innerHTML = `<div class="msg-content">${escapeHtml(message)}</div>`;
        messagesEl.appendChild(userMsg);

        // Clear input
        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;
        counter.textContent = '0 car.';

        // Add AI placeholder
        const aiMsg = document.createElement('div');
        aiMsg.className = 'msg msg-ai';
        aiMsg.innerHTML = `
            <div class="msg-meta"><span class="msg-model">llama-3.2 · 1b</span></div>
            <div class="msg-content"><span class="typing-indicator"><span></span><span></span><span></span></span></div>
        `;
        messagesEl.appendChild(aiMsg);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        try {
            const res = await fetch('/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    model: 'llama3.2:1b',
                    message: message,
                    context: []
                })
            });
            const data = await res.json();
            const parsed = typeof data.response === 'string'
                ? JSON.parse(data.response)
                : data.response;

            aiMsg.querySelector('.msg-content').innerHTML =
                `<p>${escapeHtml(parsed.response || 'Pas de réponse.')}</p>`;
            aiMsg.innerHTML += `
                <div class="msg-actions">
                    <button class="msg-action" onclick="copyMsg(this)">⊕ copier</button>
                    <button class="msg-action">⊞ garder</button>
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
        btn.textContent = '✓ copié';
        setTimeout(() => btn.textContent = '⊕ copier', 1500);
    }
</script>