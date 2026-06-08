<?php
/**
 * Chat page content. The sidebar + topbar shell is provided by
 * layout/chat.php; this view only owns the model bar, message list,
 * composer and the chat-specific scripts.
 *
 * @var array  $user           currentUser() snapshot (id, email, first_name, last_name, roles)
 * @var bool   $sessionClosed  true when the linked session is over (read-only chat)
 * @var string $closedReason   why the session is closed (ended / cancelled)
 */
$firstModel = $models[0] ?? null;
$defaultModelName = $firstModel ? $firstModel['name'] : 'llama3.2:1b';
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
            <div class="model-selector-wrapper">
                <button class="model-selector-btn" id="modelSelectorBtn" type="button">
                    <span class="model-tag-letter" id="modelLetter"><?= strtoupper(substr($defaultModelName, 0, 1)) ?></span>
                    <span class="model-tag-name" id="modelNameDisplay"><?= htmlspecialchars($defaultModelName) ?></span>
                    <svg class="model-selector-chevron" id="modelChevron" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>

                <div class="model-dropdown" id="modelDropdown">
                    <div class="model-dropdown-header">Modèles disponibles</div>
                    <?php if (empty($models)): ?>
                        <div class="model-dropdown-empty">Aucun modèle disponible</div>
                    <?php else: ?>
                        <?php foreach ($models as $i => $model):?>
                            <button class="model-dropdown-item<?= $i === 0 ? ' active' : '' ?>"
                                    data-model="<?= htmlspecialchars($model['name']) ?>"
                                    type="button">
                                <span class="model-dropdown-letter"><?= strtoupper(substr($model['name'], 0, 1)) ?></span>
                                <div class="model-dropdown-info">
                                    <span class="model-dropdown-name"><?= htmlspecialchars($model['name']) ?></span>
                                    <span class="model-dropdown-meta">
                                        <?php
                                        $meta = [];
                                        if ($model['version'] ?? null) {
                                            $meta[] = 'v' . $model['version'];
                                        }
                                        if ($model['context_window'] ?? null) {
                                            $meta[] = number_format($model['context_window']) . ' ctx';
                                        }
                                        echo htmlspecialchars(implode(' · ', $meta) ?: 'local · ollama');
                                        ?>
                                    </span>
                                </div>
                                <svg class="model-dropdown-check" width="16" height="16" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </button>
                        <?php endforeach; ?>
                        <?php if ($canAddModel): ?>
                            <a class="model-dropdown-item admin-action-item" href="/department-admin/addModel">
                                <span class="model-dropdown-letter">
                                    <img src="/assets/img/add.svg" style="height: 20px;">
                                </span>
                                <div class="model-dropdown-info">
                                    <span class="model-dropdown-name">Ajouter un Modèle d'IA</span>
                                </div>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
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
    const sessionMaxInputSize = <?= json_encode($env['maxInputSize'] ?? null) ?>;
    const sessionMaxTokens = <?= json_encode($env['maxTokens'] ?? null) ?>;

    const input = document.getElementById('promptInput');
    const sendBtn = document.getElementById('btnSend');
    const counter = document.getElementById('charCounter');
    const selectorBtn   = document.getElementById('modelSelectorBtn');
    const dropdown      = document.getElementById('modelDropdown');
    const chevron       = document.getElementById('modelChevron');
    const modelLetter   = document.getElementById('modelLetter');
    const modelDisplay  = document.getElementById('modelNameDisplay');
    let   selectedModel = modelDisplay?.textContent?.trim() || 'mistral:latest';

    selectorBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('open');
        chevron.classList.toggle('rotated', isOpen);
    });

    document.addEventListener('click', (e) => {
        if (!dropdown?.contains(e.target) && !selectorBtn?.contains(e.target)) {
            dropdown?.classList.remove('open');
            chevron?.classList.remove('rotated');
        }
    });

    document.querySelectorAll('.model-dropdown-item:not(.admin-action-item)').forEach(item => {
        item.addEventListener('click', () => {
            const model = item.dataset.model;
            selectedModel = model;

            modelDisplay.textContent = model;
            modelLetter.textContent  = model.charAt(0).toUpperCase();

            document.querySelectorAll('.model-dropdown-item').forEach(el => el.classList.remove('active'));
            item.classList.add('active');

            dropdown.classList.remove('open');
            chevron.classList.remove('rotated');
        });
    });

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

            // Mirror the code language onto the <pre> so the CSS badge
            // (pre[data-lang]::before) shows it (e.g. "PHP").
            if (!pre.hasAttribute('data-lang')) {
                const code = pre.querySelector('code');
                const lang = code && code.className.match(/language-([\w-]+)/);
                if (lang) pre.setAttribute('data-lang', lang[1]);
            }

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'code-copy';
            btn.textContent = 'Copier';
            btn.addEventListener('click', () => {
                const code = pre.querySelector('code') || pre;
                window.copyToClipboard(code.textContent).then(() => {
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
            renderMarkdown(el.getAttribute('data-markdown') ?? '', el);
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

        // Validate max_input_size limit if set
        if (sessionMaxInputSize !== null && message.length > sessionMaxInputSize) {
            const errorMsg = document.createElement('div');
            errorMsg.className = 'msg msg-error';
            errorMsg.innerHTML = `<div class="msg-content"><p class="msg-error">Votre message dépasse la limite de ${sessionMaxInputSize} caractères.</p></div>`;
            const messagesEl = document.getElementById('messages');
            messagesEl.appendChild(errorMsg);
            messagesEl.scrollTop = messagesEl.scrollHeight;
            return;
        }

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
            <div class="msg-meta"><span class="msg-model">${escapeHtml(selectedModel)}</span></div>
            <div class="msg-content"><span class="typing-indicator"><span></span><span></span><span></span></span></div>
        `;
        messagesEl.appendChild(aiMsg);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        currentAbort = new AbortController();
        setSending(true);

        try {
            const startTime = performance.now();

            const res = await fetch('/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    model: selectedModel,
                    message: message,
                    conversation_id: conversationId,
                    context: conversationContext
                })
            });


            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error("Response n'est pas du JSON valide", text);
                aiMsg.querySelector('.msg-content').innerHTML =
                    '<p class="msg-error">Réponse invalide du serveur.</p>';
                return;
            }

            // Server-side limits / auth: the endpoint returns an error JSON
            // with a non-2xx status (429 token cap, 422 char cap, 403, etc.).
            if (!res.ok || data.error) {
                const msg = data.error ?? 'Une erreur est survenue.';
                aiMsg.querySelector('.msg-content').innerHTML =
                    `<p class="msg-error">${escapeHtml(msg)}</p>`;
                return;
            }

            const endTime = performance.now();
            const durationStr = ((endTime - startTime) / 1000).toFixed(2) + 's';

            const responseText = data.response ?? 'Pas de réponse.';
            renderMarkdown(responseText, aiMsg.querySelector('.msg-content'));

            
            const newConvId   = data.conversation_id   ?? null;
            const newConvName = data.conversation_name ?? 'Nouvelle conversation';
            const reply =  data.response || 'Pas de réponse.';
            const inputTokens  =  data.prompt_eval_count || 0;
            const outputTokens =  data.eval_count || 0;
            const totalTokens  = inputTokens + outputTokens;

            
            if (newConvId && !conversationId) {
                window._activeConvId = newConvId;
                history.replaceState(null, '', `/chat/${newConvId}`);
                
                const convNameEl = document.getElementById('convName');
                if (convNameEl) convNameEl.textContent = newConvName;
            }

            if (newConvName){
                const convNameEl = document.getElementById('convName');
                if (convNameEl) convNameEl.textContent = newConvName;

                const activeConvId = newConvId ?? conversationId;
                const sidebarLink = document.querySelector(
                    `#convList .conv-item[href="/chat/${activeConvId}"]`
                );
                if (sidebarLink) {
                    const titleEl = sidebarLink.querySelector('.conv-title');
                    if (titleEl) titleEl.textContent = newConvName;
                }
            }
            if (newConvId) {
                addConvToSidebar(newConvId, newConvName);
            }
            const actions = document.createElement('div');
            actions.className = 'msg-actions';
            actions.innerHTML = `
                    <button class="msg-action" onclick="copyMsg(this)">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        Copier
                    </button>

                    <span class="msg-stat" title="Temps de réponse">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        ${durationStr}
                    </span>
                    <span class="msg-stat" title="${inputTokens} entrée + ${outputTokens} sortie">
                        ${totalTokens} tokens
                    </span>
            `;
            aiMsg.appendChild(actions);
        } catch (err) {
            aiMsg.querySelector('.msg-content').innerHTML = err.name === 'AbortError'
                ? '<p class="msg-error">Génération interrompue.</p>'
                : '<p class="msg-error">Erreur de connexion au modèle.</p>';
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
        window.copyToClipboard(text);
        const original = btn.innerHTML;
        btn.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> copié`;
        setTimeout(() => btn.innerHTML = original, 1500);
    }

</script>


