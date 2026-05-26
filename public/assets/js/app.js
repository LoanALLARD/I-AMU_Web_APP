/**
 * I-AMU - JavaScript principal
 * Gestion du chat en AJAX, création de conversations, auto-resize du textarea.
 */

// ─── Icônes inline (synchros avec app/helpers/icons.php) ─────────────
const ICON_WARNING = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon text-warning" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`;
const ICON_CHECK = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="icon" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>`;

document.addEventListener('DOMContentLoaded', () => {
    const chatForm = document.getElementById('chat-form');
    const chatMessages = document.getElementById('chat-messages');
    const promptInput = document.getElementById('prompt-input');
    const btnSend = document.getElementById('btn-send');
    const modelSelect = document.getElementById('model-select');

    // ─── Auto-resize du textarea ────────────────────────────
    if (promptInput) {
        promptInput.addEventListener('input', () => {
            promptInput.style.height = 'auto';
            promptInput.style.height = Math.min(promptInput.scrollHeight, 150) + 'px';
        });

        // Envoi avec Entrée (Shift+Entrée pour saut de ligne)
        promptInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm?.dispatchEvent(new Event('submit'));
            }
        });
    }

    // Configuration de marked.js pour le rendu Markdown
    if (window.marked) {
        marked.setOptions({
            breaks: true,
            gfm: true,
        });
    }

    // ─── Envoi de prompt en STREAMING (SSE) ─────────────────
    if (chatForm) {
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const prompt = promptInput.value.trim();
            if (!prompt) return;

            const conversationId = chatForm.querySelector('[name="conversation_id"]').value;
            const modelId = modelSelect?.value;

            // Affiche le message utilisateur
            appendMessage('user', prompt);
            promptInput.value = '';
            promptInput.style.height = 'auto';

            // Crée la bulle de réponse IA (vide, qui va se remplir)
            const aiMessage = document.createElement('div');
            aiMessage.className = 'message message-ai';
            const aiContent = document.createElement('div');
            aiContent.className = 'message-content streaming-cursor';
            aiMessage.appendChild(aiContent);
            chatMessages.appendChild(aiMessage);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            btnSend.disabled = true;
            let fullText = '';

            try {
                const response = await fetch('/chat/stream', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        conversation_id: conversationId,
                        model_id: modelId,
                        prompt: prompt,
                    }),
                });

                if (!response.ok) throw new Error('HTTP ' + response.status);

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });

                    // Parse les événements SSE (séparés par double newline)
                    const events = buffer.split('\n\n');
                    buffer = events.pop(); // garde le dernier morceau incomplet

                    for (const evt of events) {
                        const lines = evt.split('\n');
                        let eventType = 'message';
                        let dataStr = '';
                        for (const line of lines) {
                            if (line.startsWith('event:')) eventType = line.slice(6).trim();
                            if (line.startsWith('data:')) dataStr += line.slice(5).trim();
                        }
                        if (!dataStr) continue;

                        let data;
                        try { data = JSON.parse(dataStr); } catch { continue; }

                        if (eventType === 'chunk') {
                            fullText += data.text;
                            aiContent.innerHTML = renderMarkdown(fullText);
                            chatMessages.scrollTop = chatMessages.scrollHeight;
                        } else if (eventType === 'done') {
                            aiContent.classList.remove('streaming-cursor');
                            aiContent.innerHTML = renderMarkdown(fullText);
                            attachCodeCopyButtons(aiContent);
                            // Métadonnées
                            const meta = document.createElement('div');
                            meta.className = 'message-meta';
                            meta.innerHTML = `<span class="meta-latency">${data.latency}ms</span><span class="meta-model">${escapeHtml(data.model)}</span>`;
                            aiMessage.appendChild(meta);
                        } else if (eventType === 'error') {
                            aiContent.classList.remove('streaming-cursor');
                            aiContent.innerHTML = ICON_WARNING + ' ' + escapeHtml(data.message);
                        }
                    }
                }
            } catch (err) {
                aiContent.classList.remove('streaming-cursor');
                aiContent.innerHTML = ICON_WARNING + ' Erreur de communication avec le serveur.';
                console.error('Erreur streaming:', err);
            } finally {
                btnSend.disabled = false;
                promptInput.focus();
            }
        });
    }

    // ─── Création de nouvelle conversation ──────────────────
    // Attache l'événement à TOUS les boutons (sidebar + centre de l'état vide)
    const newConvButtons = [
        document.getElementById('btn-new-conversation'),
        document.getElementById('btn-start-conversation'),
    ].filter(Boolean);

    newConvButtons.forEach(btn => {
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            const originalText = btn.textContent;
            btn.textContent = 'Création...';

            try {
                const response = await fetch('/chat/create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ name: 'Nouvelle conversation', type: 'FREE' }),
                });

                if (!response.ok) throw new Error('HTTP ' + response.status);

                const data = await response.json();
                if (data.success) {
                    window.location.href = '/chat/' + data.conversation_id;
                } else {
                    alert(data.error || 'Erreur lors de la création.');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            } catch (err) {
                console.error('Erreur création conversation:', err);
                alert('Erreur lors de la création de la conversation. Vérifiez la console.');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    });

    // ─── Helpers ────────────────────────────────────────────
    function appendMessage(role, text, isLoading = false, meta = null) {
        if (!chatMessages) return null;

        const div = document.createElement('div');
        div.className = `message message-${role === 'user' ? 'user' : 'ai'}`;

        let html = `<div class="message-content">${isLoading ? '<span class="loading-dots">' + escapeHtml(text) + '</span>' : escapeHtml(text).replace(/\n/g, '<br>')}</div>`;

        if (meta) {
            html += `<div class="message-meta">`;
            if (meta.latency) html += `<span class="meta-latency">${meta.latency}ms</span>`;
            if (meta.model) html += `<span class="meta-model">${escapeHtml(meta.model)}</span>`;
            html += `</div>`;
        }

        div.innerHTML = html;
        chatMessages.appendChild(div);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return div;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ─── Rendu Markdown ─────────────────────────────────────
    function renderMarkdown(text) {
        if (!window.marked) return escapeHtml(text).replace(/\n/g, '<br>');
        try {
            return marked.parse(text);
        } catch (e) {
            return escapeHtml(text).replace(/\n/g, '<br>');
        }
    }

    // Transforme les <pre><code> en blocs de code stylés avec bouton copier
    function attachCodeCopyButtons(container) {
        const blocks = container.querySelectorAll('pre');
        blocks.forEach(pre => {
            if (pre.closest('.code-block')) return; // déjà traité

            const code = pre.querySelector('code');
            if (!code) return;

            // Détection du langage
            let lang = 'code';
            const cls = code.className.match(/language-(\w+)/);
            if (cls) lang = cls[1];

            // Coloration syntaxique
            if (window.hljs) {
                try { hljs.highlightElement(code); } catch (e) {}
            }

            // Wrapper
            const wrapper = document.createElement('div');
            wrapper.className = 'code-block';
            const header = document.createElement('div');
            header.className = 'code-block-header';
            header.innerHTML = `<span class="code-lang">${lang}</span>`;

            const copyBtn = document.createElement('button');
            copyBtn.className = 'code-copy-btn';
            copyBtn.textContent = 'Copier';
            copyBtn.addEventListener('click', () => {
                navigator.clipboard.writeText(code.textContent).then(() => {
                    copyBtn.innerHTML = ICON_CHECK + ' Copié';
                    copyBtn.classList.add('copied');
                    setTimeout(() => {
                        copyBtn.textContent = 'Copier';
                        copyBtn.classList.remove('copied');
                    }, 1500);
                });
            });
            header.appendChild(copyBtn);

            pre.parentNode.insertBefore(wrapper, pre);
            wrapper.appendChild(header);
            wrapper.appendChild(pre);
        });
    }

    // ─── Rendu Markdown des messages déjà présents (historique) ──
    if (chatMessages) {
        chatMessages.querySelectorAll('.message-ai .message-content').forEach(el => {
            const raw = el.getAttribute('data-raw') || el.textContent;
            el.innerHTML = renderMarkdown(raw);
            attachCodeCopyButtons(el);
        });
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});
