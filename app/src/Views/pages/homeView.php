<?php
/**
 * Chat page content. The sidebar + topbar shell is provided by
 * Layout/chat.php; this view only owns the model bar, message list,
 * composer and the chat-specific scripts.
 *
 * @var array  $user           currentUser() snapshot (id, email, first_name, last_name, roles)
 * @var bool   $sessionClosed  true when the linked session is over (read-only chat)
 * @var string $closedReason   why the session is closed (ended / cancelled)
 */
$sessionClosed = $sessionClosed ?? false;
$closedReason = $closedReason ?? '';
$conversation = $conversation ?? null;
$env = $env ?? null;
$inSession = ($env['mode'] ?? 'libre') === 'session';
$messages = $messages ?? [];
$hasMessages = $messages !== [];
?>
<div class="chat-container">
    <div class="chat-area">

        <div class="model-bar">
            <div class="model-tags">
                <button class="model-tag active" data-model="llama3.2:1b">
                    <span class="model-tag-letter">A</span>
                    <span class="model-tag-name">llama3.2:1b</span>
                    <span class="model-tag-badge">local · ollama</span>
                </button>
            </div>
        </div>

        <div class="messages" id="messages">
            <?php if ($hasMessages): ?>
                <?php foreach ($messages as $m): ?>
                    <div class="msg msg-user">
                        <div class="msg-content"><?= htmlspecialchars(trim($m['prompt'])) ?></div>
                    </div>
                    <div class="msg msg-ai">
                        <div class="msg-meta">
                            <span class="msg-model"><?= htmlspecialchars($m['model']) ?></span>
                        </div>
                        <div class="msg-content" data-markdown="<?= htmlspecialchars($m['response'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" id="emptyState">
                    <div class="empty-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                        </svg>
                    </div>
                    <?php if ($sessionClosed): ?>
                        <h2>Session terminée</h2>
                        <p><?= htmlspecialchars($closedReason) ?> Cette conversation est en lecture seule.</p>
                    <?php else: ?>
                        <h2>Bonjour <?= htmlspecialchars($user['first_name'] ?? '') ?> !</h2>
                        <p>Posez une question à l'IA ou sélectionnez un modèle pour commencer.</p>
                        <div class="empty-suggestions">
                            <button class="suggestion-chip" onclick="fillPrompt(this)">Explique-moi les pointeurs en C</button>
                            <button class="suggestion-chip" onclick="fillPrompt(this)">Écris une fonction de tri en Python</button>
                            <button class="suggestion-chip" onclick="fillPrompt(this)">Qu'est-ce que le pattern MVC ?</button>
                        </div>
                        <?php if (!$inSession && in_array('student', $user['roles'] ?? [], true)): ?>
                            <a href="/sessions/join" class="empty-join-link">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                                    <polyline points="10 17 15 12 10 7" />
                                    <line x1="15" y1="12" x2="3" y2="12" />
                                </svg>
                                Rejoindre une session avec un code
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="input-bar">
            <?php if ($sessionClosed): ?>
                <div class="session-closed-banner">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    <?= htmlspecialchars($closedReason) ?> Vous ne pouvez plus envoyer de message.
                </div>
            <?php endif; ?>
            <div class="input-wrapper">
                <textarea id="promptInput"
                    placeholder="<?= $sessionClosed ? 'Session terminée — envoi désactivé' : 'Écrivez votre message…' ?>"
                    rows="1" <?= $sessionClosed ? 'disabled' : 'autofocus' ?>></textarea>
                <button class="btn-send" id="btnSend" disabled title="Envoyer">
                    <svg class="icon-send" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13" />
                        <polygon points="22 2 15 22 11 13 2 9 22 2" />
                    </svg>
                    <svg class="icon-stop" width="15" height="15" viewBox="0 0 24 24" fill="currentColor"
                        aria-hidden="true">
                        <rect x="6" y="6" width="12" height="12" rx="2" />
                    </svg>
                </button>
            </div>
            <div class="input-footer">
                <span class="input-hint">Entrée pour envoyer · Maj+Entrée pour un retour à la ligne</span>
                <span class="input-counter" id="charCounter">0 car.</span>
            </div>
        </div>
    </div>
</div>

<script>
    const conversationId = <?= json_encode($conversation['id'] ?? null) ?>;
    const conversationContext = <?= json_encode($messages ?? []) ?>;
    console.log("CONVERSATION CONTEXT:", conversationContext);

    const input = document.getElementById('promptInput');
    const sendBtn = document.getElementById('btnSend');
    const counter = document.getElementById('charCounter');

    // Render an AI reply's markdown to sanitized HTML, then syntax-highlight
    // its code blocks. Falls back to escaped plain text if the libs are absent.
    function renderMarkdown(text, el) {
        if (!el) return;
        el.innerHTML = (window.marked && window.DOMPurify)
            ? DOMPurify.sanitize(marked.parse(text ?? '', { breaks: true, gfm: true }))
            : `<p>${escapeHtml(text ?? '')}</p>`;
        if (window.hljs) {
            el.querySelectorAll('pre code').forEach((block) => hljs.highlightElement(block));
        }
        addCodeCopyButtons(el);
    }

    // Adds a "Copier" button to each code block so the snippet alone can be
    // copied (separate from the message-level copy action).
    function addCodeCopyButtons(container) {
        container.querySelectorAll('pre').forEach((pre) => {
            // Wrap the <pre> so the button is pinned to a non-scrolling parent
            // (the <pre> itself scrolls horizontally for long lines).
            if (pre.parentElement.classList.contains('code-block')) return;
            const wrap = document.createElement('div');
            wrap.className = 'code-block';
            pre.parentNode.insertBefore(wrap, pre);
            wrap.appendChild(pre);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'code-copy';
            btn.textContent = 'Copier';
            btn.addEventListener('click', () => {
                const code = pre.querySelector('code') || pre;
                navigator.clipboard.writeText(code.textContent).then(() => {
                    btn.textContent = 'Copié';
                    btn.classList.add('is-copied');
                    setTimeout(() => {
                        btn.textContent = 'Copier';
                        btn.classList.remove('is-copied');
                    }, 1500);
                });
            });
            wrap.appendChild(btn);
        });
    }

    // On load: render persisted AI bubbles (raw markdown -> HTML), then jump to
    // the latest message so a reopened thread starts at the bottom.
    (function () {
        document.querySelectorAll('.msg-ai .msg-content[data-markdown]').forEach((el) => {
            renderMarkdown(el.textContent, el);
        });
        const m = document.getElementById('messages');
        if (m) m.scrollTop = m.scrollHeight;
    })();

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

    // Tracks the in-flight request so the send button can double as a stop
    // button while the model is generating.
    let currentAbort = null;

    function setSending(on) {
        sendBtn.classList.toggle('is-stop', on);
        sendBtn.title = on ? 'Arrêter la génération' : 'Envoyer';
        sendBtn.disabled = on ? false : !input.value.trim();
    }

    sendBtn?.addEventListener('click', () => {
        if (sendBtn.classList.contains('is-stop')) {
            currentAbort?.abort();
            return;
        }
        if (input.value.trim()) sendMessage();
    });

    function fillPrompt(btn) {
        input.value = btn.textContent;
        input.dispatchEvent(new Event('input'));
        input.focus();
    }

    async function sendMessage() {
        if (input.disabled || currentAbort) return;
        const message = input.value.trim();
        if (!message) return;

        const messagesEl = document.getElementById('messages');
        const emptyState = document.getElementById('emptyState');
        if (emptyState) emptyState.style.display = 'none';

        const userMsg = document.createElement('div');
        userMsg.className = 'msg msg-user';
        userMsg.innerHTML = `<div class="msg-content">${escapeHtml(message)}</div>`;
        messagesEl.appendChild(userMsg);

        input.value = '';
        input.style.height = 'auto';
        counter.textContent = '0 car.';

        const aiMsg = document.createElement('div');
        aiMsg.className = 'msg msg-ai';
        aiMsg.innerHTML = `
            <div class="msg-meta"><span class="msg-model">llama3.2:1b</span></div>
            <div class="msg-content"><span class="typing-indicator"><span></span><span></span><span></span></span></div>
        `;
        messagesEl.appendChild(aiMsg);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        currentAbort = new AbortController();
        setSending(true);

        try {
            const res = await fetch('/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    model: 'llama3.2:1b',
                    message: message,
                    conversation_id: conversationId,
                    context: conversationContext
                })
            });
            const text = await res.text();
            console.log("RAW RESPONSE:", text);

            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error("Response n'est pas du JSON valide", text);
                return;
            }

            const parsed = typeof data.response === 'string'
                ? JSON.parse(data.response)
                : data.response;

            aiMsg.querySelector('.msg-content').innerHTML =
                parseMarkdown(parsed.response || 'Pas de réponse.');

            
            const newConvId   = data.conversation_id   ?? null;
            const newConvName = data.conversation_name ?? 'Nouvelle conversation';

            if (newConvId && !conversationId) {
                window._activeConvId = newConvId;
                history.replaceState(null, '', `/chat/${newConvId}`);
                
                const convNameEl = document.getElementById('convName');
                if (convNameEl) convNameEl.textContent = newConvName;
            }

            if (newConvId) {
                addConvToSidebar(newConvId, newConvName);
            }
            aiMsg.innerHTML += `
                <div class="msg-actions">
                    <button class="msg-action" onclick="copyMsg(this)">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        Copier
                    </button>
                    <button class="msg-action">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                        Garder
                    </button>
                </div>`;
        } catch (err) {
            aiMsg.querySelector('.msg-content').innerHTML = err.name === 'AbortError'
                ? `<p class="msg-error">Génération interrompue.</p>`
                : `<p class="msg-error">Erreur de connexion au modèle.</p>`;
        } finally {
            currentAbort = null;
            setSending(false);
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

function parseMarkdown(text) {
    if (!text) return '';

    // --- Étape 0 : normaliser les fins de ligne ---
    let raw = text.trim().replace(/\r\n/g, '\n').replace(/\r/g, '\n');

    // --- Étape 1 : extraire les blocs de code (protégés des autres regex) ---
    // On les remplace par des tokens neutres pour éviter toute transformation dessus
    const codeBlocks = [];
    raw = raw.replace(/```(\w*)\n?([\s\S]*?)```/g, (_, lang, code) => {
        const idx = codeBlocks.length;
        const langAttr = lang ? ` data-lang="${escapeHtml(lang)}"` : '';
        codeBlocks.push(
            `<pre${langAttr}><code>${escapeHtml(code.trim())}</code></pre>`
        );
        return `\x00CODE_BLOCK_${idx}\x00`;
    });

    // --- Étape 2 : inline code (protégé aussi) ---
    const inlineCodes = [];
    raw = raw.replace(/`([^`\n]+)`/g, (_, code) => {
        const idx = inlineCodes.length;
        inlineCodes.push(`<code>${escapeHtml(code)}</code>`);
        return `\x00INLINE_${idx}\x00`;
    });

    // --- Étape 3 : découper en blocs (paragraphes séparés par \n\n) ---
    const blocks = raw.split(/\n{2,}/);

    const processedBlocks = blocks.map(block => {
        const trimmed = block.trim();
        if (!trimmed) return '';

        // Token code block → restituer directement, pas de wrap <p>
        if (/^\x00CODE_BLOCK_\d+\x00$/.test(trimmed)) {
            return trimmed;
        }

        // --- Headings (en début de bloc) ---
        if (/^###\s/.test(trimmed)) return `<h3>${inlineFormat(trimmed.slice(4))}</h3>`;
        if (/^##\s/.test(trimmed))  return `<h2>${inlineFormat(trimmed.slice(3))}</h2>`;
        if (/^#\s/.test(trimmed))   return `<h1>${inlineFormat(trimmed.slice(2))}</h1>`;

        // --- Listes (lignes commençant par - ou *) ---
        const lines = trimmed.split('\n');
        const isListBlock = lines.every(l => /^[-*]\s/.test(l.trim()) || l.trim() === '');
        if (isListBlock) {
            const items = lines
                .filter(l => /^[-*]\s/.test(l.trim()))
                .map(l => `<li>${inlineFormat(l.trim().slice(2))}</li>`)
                .join('');
            return `<ul>${items}</ul>`;
        }

        // --- Listes numérotées ---
        const isOrderedList = lines.every(l => /^\d+\.\s/.test(l.trim()) || l.trim() === '');
        if (isOrderedList) {
            const items = lines
                .filter(l => /^\d+\.\s/.test(l.trim()))
                .map(l => `<li>${inlineFormat(l.trim().replace(/^\d+\.\s/, ''))}</li>`)
                .join('');
            return `<ol>${items}</ol>`;
        }

        // --- Paragraphe normal : \n simples → <br> à l'intérieur ---
        const content = lines.map(l => inlineFormat(l)).join('<br>');
        return `<p>${content}</p>`;
    });

    let html = processedBlocks.filter(Boolean).join('\n');

    // --- Étape 4 : réinjecter les tokens protégés ---
    html = html.replace(/\x00CODE_BLOCK_(\d+)\x00/g, (_, i) => codeBlocks[i]);
    html = html.replace(/\x00INLINE_(\d+)\x00/g,     (_, i) => inlineCodes[i]);

    return html;
}

// Formatage inline (bold, italic, liens) — jamais appelé sur du code
function inlineFormat(text) {
    return text
        .replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
        .replace(/\*\*(.+?)\*\*/g,     '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g,         '<em>$1</em>')
        .replace(/~~(.+?)~~/g,         '<del>$1</del>')
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-markdown]').forEach(el => {
        const raw = el.getAttribute('data-markdown');
        el.innerHTML = parseMarkdown(raw);
    });
});
</script>
