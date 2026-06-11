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
$messageDocuments = $messageDocuments ?? [];
$hasMessages = $messages !== [];
$canAddModel = $canAddModel ?? false;
?>
<div class="chat-container">
    <div class="chat-area">

        <div class="model-bar">
            <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                <div class="model-selector-wrapper">
                    <button class="model-selector-btn" id="modelSelectorBtn" type="button">
                        <span class="model-tag-letter"
                              id="modelLetter"><?= strtoupper(substr($defaultModelName, 0, 1)) ?></span>
                        <span class="model-tag-name"
                              id="modelNameDisplay"><?= htmlspecialchars($defaultModelName) ?></span>
                        <svg class="model-selector-chevron" id="modelChevron" width="14" height="14" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                             stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9" />
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
                                        if ($model['size'] ?? null) {
                                            $meta[] = $model['size'];
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

                <?php if ($inSession && ($sessionDocuments ?? []) !== []): ?>
                    <div class="model-selector-wrapper">
                        <button class="model-selector-btn" id="docSelectorBtn" type="button">
                            <?= icon('book', '', 14) ?>
                            <span class="model-tag-name">Documents (<?= count($sessionDocuments) ?>)</span>
                            <svg class="model-selector-chevron" id="docChevron" width="14" height="14" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                 stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <div class="model-dropdown" id="docDropdown">
                            <div class="model-dropdown-header">Documents de la session</div>
                            <?php foreach ($sessionDocuments as $doc): ?>
                                <a class="model-dropdown-item"
                                   href="/documents/session_<?= (int) $doc->sessionId() ?>/<?= (int) $doc->id() ?>"
                                   target="_blank" rel="noopener" style="text-decoration:none;color:inherit;"
                                   title="<?= htmlspecialchars($doc->originalName()) ?>">
                                    <span class="model-dropdown-letter"><?= icon('book', '', 13) ?></span>
                                    <div class="model-dropdown-info" style="min-width:0;">
                                        <span class="model-dropdown-name"
                                              style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                            <?= htmlspecialchars($doc->originalName()) ?>
                                        </span>
                                        <span class="model-dropdown-meta">
                                            <?= htmlspecialchars($doc->kindLabel()) ?> ·
                                            <?= htmlspecialchars($doc->humanSize()) ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="messages" id="messages">
            <?php foreach ($messages as $m): ?>
                <?php $docsOfMsg = $messageDocuments[$m['id'] ?? 0] ?? []; ?>
                <div class="msg msg-user">
                    <div class="msg-content"><?= htmlspecialchars(trim($m['prompt'])) ?></div>
                    <?php if ($docsOfMsg !== []): ?>
                        <div class="msg-docs">
                            <?php foreach ($docsOfMsg as $doc): ?>
                                <a class="msg-doc"
                                   href="/documents/conversation_<?= (int) ($conversation['id'] ?? 0) ?>/<?= (int) $doc->id() ?>"
                                   target="_blank" rel="noopener"
                                   title="<?= htmlspecialchars($doc->kindLabel() . ' · ' . $doc->humanSize()) ?>">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span><?= htmlspecialchars($doc->originalName()) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="msg msg-ai" data-interaction="<?= (int) $m['id'] ?>" data-feedback="<?= $m['feedback'] === null ? '' : (int) $m['feedback'] ?>">
                    <div class="msg-meta">
                        <span class="msg-model"><?= htmlspecialchars($m['model']) ?></span>
                    </div>
                    <div class="msg-content" data-markdown="<?= htmlspecialchars($m['response'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <?php
                        $inTok = (int) ($m['inputTokens'] ?? 0);
                        $outTok = (int) ($m['outputTokens'] ?? 0);
                        $hasStats = ($m['latency'] ?? null) !== null || $inTok > 0 || $outTok > 0;
                        ?>
                        <?php if ($hasStats): ?>
                            <div class="msg-actions">
                                <?php if (($m['latency'] ?? null) !== null): ?>
                                    <span class="msg-stat" title="Temps de réponse">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        <?= number_format($m['latency'] / 1000, 2) ?>s
                                    </span>
                                <?php endif; ?>
                                <?php if ($inTok > 0 || $outTok > 0): ?>
                                    <span class="msg-stat" title="<?= $inTok ?> entrée + <?= $outTok ?> sortie">
                                        <?= $inTok + $outTok ?> tokens
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php /* Always rendered so the "Nouvelle conversation" button can
               restore a blank thread client-side (startBlankChat); hidden while
               the open conversation already has messages. */ ?>
            <div class="empty-state" id="emptyState"<?= $hasMessages ? ' style="display:none;"' : '' ?>>
                <div class="empty-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"
                        stroke-linecap="round" stroke-linejoin="round">
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
            <div class="chat-attachments" id="chatAttachments" hidden></div>
            <div class="input-wrapper">
                <?php
                $docCfg     = $documentsConfig ?? ['enabled' => true, 'acceptExts' => ['pdf', 'md', 'txt']];
                $acceptMap  = ['pdf' => '.pdf,application/pdf', 'md' => '.md,.markdown,text/markdown', 'txt' => '.txt,text/plain'];
                $acceptExts = !empty($docCfg['acceptExts']) ? $docCfg['acceptExts'] : ['pdf', 'md', 'txt'];
                $acceptAttr = implode(',', array_map(static fn ($e) => $acceptMap[$e] ?? '', $acceptExts));
                $typesLabel = implode(', ', array_map('strtoupper', $acceptExts));
                ?>
                <?php if (!$sessionClosed && !empty($docCfg['enabled'])): ?>
                <button type="button" class="btn-attach" id="btnAttach"
                    title="Joindre un document (<?= htmlspecialchars($typesLabel) ?>)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
                    </svg>
                </button>
                <input type="file" id="docFileInput" hidden multiple
                    accept="<?= htmlspecialchars($acceptAttr) ?>">
                <?php endif; ?>
                <textarea id="promptInput"
                          placeholder="<?= $sessionClosed ? 'Session terminée — envoi désactivé' : 'Écrivez votre message…' ?>"
                          <?php if (!empty($env['maxInputSize'])): ?>maxlength="<?= (int) $env['maxInputSize'] ?>"<?php endif; ?>
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

<style>
    .input-wrapper .btn-attach {
        align-self: flex-end;
        margin-bottom: 4px;
        background: transparent;
        border: none;
        color: var(--text-muted, #8a8a8a);
        cursor: pointer;
        padding: 6px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
    }
    .input-wrapper .btn-attach:hover:not(:disabled) { background: rgba(127, 127, 127, .15); color: inherit; }
    .input-wrapper .btn-attach:disabled { opacity: .4; cursor: not-allowed; }
    .chat-attachments { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 2px .5rem; }
    .chat-attachments[hidden] { display: none; }
    /* Documents shown under the message they were sent with (provenance). */
    .msg-docs { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
    .msg-user .msg-docs { justify-content: flex-end; }
    .msg-doc {
        display: inline-flex; align-items: center; gap: 5px; max-width: 240px;
        padding: 4px 9px; border-radius: 8px;
        background: rgba(127, 127, 127, .14); color: inherit;
        font-size: 12px; text-decoration: none;
    }
    .msg-doc span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .msg-doc:hover { background: rgba(127, 127, 127, .22); }
    .attach-chip {
        display: inline-flex; align-items: center; gap: 6px; max-width: 240px;
        padding: 4px 8px; border-radius: 999px;
        background: rgba(127, 127, 127, .14); font-size: 12px;
    }
    .attach-chip a {
        color: inherit; text-decoration: none; white-space: nowrap;
        overflow: hidden; text-overflow: ellipsis; max-width: 170px;
    }
    .attach-chip a:hover { text-decoration: underline; }
    .attach-chip .attach-remove {
        background: none; border: none; color: inherit; cursor: pointer;
        font-size: 15px; line-height: 1; opacity: .6; padding: 0 2px;
    }
    .attach-chip .attach-remove:hover { opacity: 1; }
</style>
<script>
    const conversationId = <?= json_encode($conversation['id'] ?? null) ?>;
    const conversationContext = <?= json_encode($messages ?? []) ?>;
    const sessionMaxInputSize = <?= json_encode($env['maxInputSize'] ?? null) ?>;
    const sessionMaxTokens = <?= json_encode($env['maxTokens'] ?? null) ?>;
    // Session of the current environment (null in free chat). Sent with the
    // first message of a blank chat so the server creates the conversation in
    // the right environment (free vs session).
    const sessionEnvId = <?= json_encode($env['sessionId'] ?? null) ?>;
    let activeConvId = conversationId;
    const csrfToken = <?= json_encode(\Core\Csrf::generateToken()) ?>;

    const input = document.getElementById('promptInput');
    const sendBtn = document.getElementById('btnSend');
    const counter = document.getElementById('charCounter');
    const selectorBtn   = document.getElementById('modelSelectorBtn');
    const dropdown      = document.getElementById('modelDropdown');
    const chevron       = document.getElementById('modelChevron');
    const modelLetter   = document.getElementById('modelLetter');
    const modelDisplay  = document.getElementById('modelNameDisplay');
    let   selectedModel = modelDisplay?.textContent?.trim() || 'mistral:latest';

    // Documents dropdown (session env) — reuses the model picker widget.
    const docBtn = document.getElementById('docSelectorBtn');
    const docDropdown = document.getElementById('docDropdown');
    const docChevron = document.getElementById('docChevron');

    selectorBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        docDropdown?.classList.remove('open');
        docChevron?.classList.remove('rotated');
        const isOpen = dropdown.classList.toggle('open');
        chevron.classList.toggle('rotated', isOpen);
    });

    docBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown?.classList.remove('open');
        chevron?.classList.remove('rotated');
        const isOpen = docDropdown.classList.toggle('open');
        docChevron?.classList.toggle('rotated', isOpen);
    });

    document.addEventListener('click', (e) => {
        if (!dropdown?.contains(e.target) && !selectorBtn?.contains(e.target)) {
            dropdown?.classList.remove('open');
            chevron?.classList.remove('rotated');
        }
        if (!docDropdown?.contains(e.target) && !docBtn?.contains(e.target)) {
            docDropdown?.classList.remove('open');
            docChevron?.classList.remove('rotated');
        }
    });

    document.querySelectorAll('.model-dropdown-item:not(.admin-action-item)').forEach(item => {
        item.addEventListener('click', () => {
            const model = item.dataset.model;
            selectedModel = model;

            modelDisplay.textContent = model;
            modelLetter.textContent = model.charAt(0).toUpperCase();

            dropdown.querySelectorAll('.model-dropdown-item').forEach(el => el.classList.remove('active'));
            item.classList.add('active');

            dropdown.classList.remove('open');
            chevron.classList.remove('rotated');
        });
    });

    // Markdown rendering (window.renderMarkdown) is provided by the shared
    // assets/js/markdown.js module, loaded synchronously in <head> by the
    // layout — available here at parse time and for live streaming below.

    // On load: render persisted AI bubbles (raw markdown -> HTML), attach the
    // satisfaction thumbs (restoring the student's previous rating), then jump
    // to the latest message so a reopened thread starts at the bottom.
    (function () {
        document.querySelectorAll('.msg-ai .msg-content[data-markdown]').forEach((el) => {
            renderMarkdown(el.getAttribute('data-markdown') ?? '', el);
        });
        document.querySelectorAll('.msg-ai[data-interaction]').forEach((el) => {
            const id = parseInt(el.getAttribute('data-interaction') ?? '0', 10);
            const raw = el.getAttribute('data-feedback');
            attachFeedback(el, id, raw ? parseInt(raw, 10) : 0);
        });
        const m = document.getElementById('messages');
        if (m) m.scrollTop = m.scrollHeight;
    })();

    input?.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 200) + 'px';
        const len = input.value.length;
        const over = sessionMaxInputSize !== null && len >= sessionMaxInputSize;
        sendBtn.disabled = !input.value.trim() || over;
        counter.textContent = sessionMaxInputSize !== null
            ? len + ' / ' + sessionMaxInputSize + ' car.'
            : len + ' car.';
        counter.classList.toggle('is-limit', over);
    });
    input?.dispatchEvent(new Event('input'));

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

    // "Nouvelle conversation": open a blank chat WITHOUT creating it in the
    // database. We only reset the view client-side; the conversation is
    // persisted server-side when the first message is sent (handleChat with a
    // null conversation_id). Staying client-side keeps the current environment
    // (free vs session) and its sidebar list intact.
    function startBlankChat() {
        if (!input || input.disabled) return; // read-only (session closed)
        activeConvId = null;

        const messagesEl = document.getElementById('messages');
        if (messagesEl) {
            messagesEl.querySelectorAll('.msg').forEach((el) => el.remove());
            const emptyState = document.getElementById('emptyState');
            if (emptyState) emptyState.style.display = '';
            messagesEl.scrollTop = 0;
        }

        const convNameEl = document.getElementById('convName');
        if (convNameEl) convNameEl.textContent = 'Nouvelle conversation';

        document.querySelectorAll('#convList .conv-row.active')
            .forEach((el) => el.classList.remove('active'));

        // Free chat has a stable blank URL; a session keeps its context (the
        // sidebar still lists that session's conversations), so leave its URL.
        if (!sessionEnvId) history.replaceState(null, '', '/chat');

        input.value = '';
        input.style.height = 'auto';
        input.dispatchEvent(new Event('input'));
        input.focus();
    }

    document.getElementById('btnNewChat')?.addEventListener('click', startBlankChat);

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

        // Show the attached documents under the message straight away (before the
        // model answers) and empty the composer; rolled back if the send fails.
        const sentDocs = getComposerDocs();
        if (sentDocs.length) renderMsgDocs(userMsg, sentDocs);
        clearComposerDocs();

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

            // A fresh (blank) chat has no conversation yet: the server creates
            // it from this first message and returns its id/name below. We pass
            // the environment's session id so it lands in the right environment.
            const wasBlank = !activeConvId;

            const res = await fetch('/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                signal: currentAbort.signal,
                body: JSON.stringify({
                    model: selectedModel,
                    message: message,
                    conversation_id: activeConvId,
                    session_id: wasBlank ? sessionEnvId : null,
                    context: conversationContext
                })
            });

            const contentEl = aiMsg.querySelector('.msg-content');

            // Pre-stream failures (auth, limits) still come back as a JSON
            // error with a non-2xx status, before any token is streamed.
            const ctype = res.headers.get('Content-Type') || '';
            if (!res.ok || ctype.includes('application/json')) {
                let msg = 'Une erreur est survenue.';
                try { const j = await res.json(); msg = j.error ?? msg; } catch (e) {}
                contentEl.innerHTML = `<p class="msg-error">${escapeHtml(msg)}</p>`;
                return;
            }

            // Read the SSE body chunk by chunk and grow the answer live.
            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let sseBuffer = '';
            let fullText = '';
            let meta = {};

            contentEl.textContent = ''; // remove the typing indicator

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                sseBuffer += decoder.decode(value, { stream: true });

                // SSE events are separated by a blank line.
                let sep;
                while ((sep = sseBuffer.indexOf('\n\n')) !== -1) {
                    const rawEvent = sseBuffer.slice(0, sep);
                    sseBuffer = sseBuffer.slice(sep + 2);

                    let eventName = 'message';
                    let dataStr = '';
                    rawEvent.split('\n').forEach((line) => {
                        if (line.startsWith('event:')) eventName = line.slice(6).trim();
                        else if (line.startsWith('data:')) dataStr += line.slice(5).trim();
                    });
                    if (!dataStr) continue;

                    let payload;
                    try { payload = JSON.parse(dataStr); } catch (e) { continue; }

                    if (eventName === 'token') {
                        fullText += payload.text ?? '';
                        // Plain text while streaming, markdown is rendered once at the end.
                        contentEl.textContent = fullText;
                        messagesEl.scrollTop = messagesEl.scrollHeight;
                    } else if (eventName === 'done') {
                        meta = payload;
                    } else if (eventName === 'error') {
                        contentEl.innerHTML = `<p class="msg-error">${escapeHtml(payload.error ?? 'Erreur du modèle.')}</p>`;
                        return;
                    }
                }
            }

            // Final markdown render once the full answer is in.
            renderMarkdown(fullText || 'Pas de réponse.', contentEl);

            const durationStr = (meta.latency_ms != null ? meta.latency_ms / 1000 : (performance.now() - startTime) / 1000).toFixed(2) + 's';
            const newConvId = meta.conversation_id ?? null;
            const newConvName = meta.conversation_name ?? 'Nouvelle conversation';
            const inputTokens = meta.prompt_eval_count || 0;
            const outputTokens = meta.eval_count || 0;
            const totalTokens = inputTokens + outputTokens;

            // Reuse the same conversation for the next messages of a fresh chat.
            if (newConvId) activeConvId = newConvId;

            if (newConvId && wasBlank) {
                window._activeConvId = newConvId;
                history.replaceState(null, '', `/chat/${newConvId}`);

                const convNameEl = document.getElementById('convName');
                if (convNameEl) convNameEl.textContent = newConvName;
            }

            if (newConvName) {
                const convNameEl = document.getElementById('convName');
                if (convNameEl) convNameEl.textContent = newConvName;

                const linkConvId = newConvId ?? conversationId;
                const sidebarLink = document.querySelector(
                    `#convList .conv-item[href="/chat/${linkConvId}"]`
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
            attachFeedback(aiMsg, meta.interaction_id ?? null, 0);
        } catch (err) {
            aiMsg.querySelector('.msg-content').innerHTML = err.name === 'AbortError'
                ? '<p class="msg-error">Génération interrompue.</p>'
                : '<p class="msg-error">Erreur de connexion au modèle.</p>';
            // Re-sync the composer with the server's true pending documents.
            if (sentDocs.length) refreshComposerDocs();
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

    // Appends the satisfaction thumbs (👍/👎) to an AI bubble, reusing the
    // bubble's actions bar when it already has one (sent message) or creating a
    // feedback-only bar (reloaded history). `current` is the stored rating
    // (1 / -1 / 0) so the active button is highlighted on load.
    function attachFeedback(aiMsg, interactionId, current) {
        if (!aiMsg || !interactionId) return;
        let bar = aiMsg.querySelector('.msg-actions');
        if (!bar) {
            bar = document.createElement('div');
            bar.className = 'msg-actions';
            aiMsg.appendChild(bar);
        }
        const span = document.createElement('span');
        span.className = 'msg-feedback';
        span.dataset.interaction = String(interactionId);
        span.innerHTML = `
            <button type="button" class="msg-action fb-btn fb-up${current === 1 ? ' is-active' : ''}" data-val="1" title="Réponse utile" aria-label="Réponse utile">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg>
            </button>
            <button type="button" class="msg-action fb-btn fb-down${current === -1 ? ' is-active' : ''}" data-val="-1" title="Réponse peu utile" aria-label="Réponse peu utile">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3-3.88Z"/></svg>
            </button>`;
        bar.appendChild(span);
    }

    // One delegated handler for every thumb (history + freshly sent). Clicking
    // the active rating clears it (sends 0 / neutral); otherwise it sets the
    // clicked value. The UI only commits once the server confirms.
    document.getElementById('messages')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('.fb-btn');
        if (!btn) return;
        const span = btn.closest('.msg-feedback');
        if (!span) return;
        const interactionId = parseInt(span.dataset.interaction ?? '0', 10);
        if (!interactionId) return;
        const value = btn.classList.contains('is-active') ? 0 : parseInt(btn.dataset.val ?? '0', 10);
        try {
            const res = await fetch('/chat/feedback', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ interaction_id: interactionId, value })
            });
            if (!res.ok) return;
            span.querySelectorAll('.fb-btn').forEach((b) => b.classList.remove('is-active'));
            if (value !== 0) btn.classList.add('is-active');
        } catch (err) {
            /* network hiccup: leave the UI unchanged */
        }
    });

    // Renders, under a user message bubble, the documents that were sent with
    // it (download links). Assigned by the attachments module below.
    let clearComposerDocs = () => {};
    let getComposerDocs = () => [];
    let refreshComposerDocs = () => {};
    function renderMsgDocs(msgEl, docs) {
        if (!msgEl || !docs || !docs.length) return;
        const wrap = document.createElement('div');
        wrap.className = 'msg-docs';
        wrap.innerHTML = docs.map((d) => `
            <a class="msg-doc" href="/documents/conversation_${activeConvId}/${d.id}" target="_blank" rel="noopener" title="${escapeHtml(d.kind)} · ${escapeHtml(d.size)}">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span>${escapeHtml(d.name)}</span>
            </a>`).join('');
        msgEl.appendChild(wrap);
    }

    // ---- Document attachments (phase 2): join files to the conversation so the
    // model takes them into account. New chat → the upload lazily creates the
    // conversation, whose id we then adopt. ----
    (function () {
        const btnAttach = document.getElementById('btnAttach');
        const fileInput = document.getElementById('docFileInput');
        const chipsEl   = document.getElementById('chatAttachments');
        if (!btnAttach || !fileInput || !chipsEl) return;

        let docs = [];

        function renderChips() {
            chipsEl.hidden = docs.length === 0;
            chipsEl.innerHTML = docs.map((d) => `
                <span class="attach-chip" title="${escapeHtml(d.name)} — ${escapeHtml(d.kind)}, ${escapeHtml(d.size)}">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <a href="/documents/conversation_${activeConvId}/${d.id}" target="_blank" rel="noopener">${escapeHtml(d.name)}</a>
                    <button type="button" class="attach-remove" title="Retirer" data-id="${d.id}">&times;</button>
                </span>`).join('');
        }

        async function refresh() {
            if (!activeConvId) { docs = []; renderChips(); return; }
            try {
                const r = await fetch('/chat/documents/' + activeConvId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) return;
                const data = await r.json();
                docs = data.documents || [];
                renderChips();
            } catch (e) { /* transient — ignore */ }
        }

        async function upload(files) {
            if (!files || !files.length) return;
            const fd = new FormData();
            fd.append('_csrf_token', csrfToken);
            fd.append('model', selectedModel);
            fd.append('conversation_id', activeConvId ?? '');
            for (const f of files) fd.append('document[]', f);

            btnAttach.disabled = true;
            try {
                const r = await fetch('/chat/documents', { method: 'POST', body: fd });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) {
                    alert(data.error || "Le document n'a pas pu être joint.");
                    return;
                }
                // Adopt the conversation created on the fly for a brand-new chat.
                if (data.conversation_id && !activeConvId) {
                    activeConvId = data.conversation_id;
                    history.replaceState(null, '', '/chat/' + activeConvId);
                    if (data.conversation && data.conversation.name) {
                        const convNameEl = document.getElementById('convName');
                        if (convNameEl) convNameEl.textContent = data.conversation.name;
                    }
                    const emptyState = document.getElementById('emptyState');
                    if (emptyState) emptyState.style.display = 'none';
                }
                (data.documents || []).forEach((d) => docs.push(d));
                renderChips();
                if (data.errors && data.errors.length) alert(data.errors.join('\n'));
            } catch (e) {
                alert('Erreur réseau pendant le téléversement.');
            } finally {
                btnAttach.disabled = false;
                fileInput.value = '';
            }
        }

        async function removeDoc(id) {
            const fd = new FormData();
            fd.append('_csrf_token', csrfToken);
            try {
                const r = await fetch('/chat/documents/' + id + '/delete', { method: 'POST', body: fd });
                if (r.ok) { docs = docs.filter((d) => String(d.id) !== String(id)); renderChips(); }
            } catch (e) { /* ignore */ }
        }

        btnAttach.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => upload(fileInput.files));
        chipsEl.addEventListener('click', (e) => {
            const btn = e.target.closest('.attach-remove');
            if (btn) removeDoc(btn.dataset.id);
        });

        // Bridge to sendMessage: read the pending docs (to show them under the
        // message at once), empty the composer, or restore it on failure.
        getComposerDocs = () => docs.slice();
        clearComposerDocs = () => { docs = []; renderChips(); };
        refreshComposerDocs = () => { refresh(); };

        refresh();
    })();

    <?php if ($inSession && !$sessionClosed): ?>
    // Live enforcement: poll the session status so that a student deactivated
    // by the teacher (or a session that closes) flips to read-only within a few
    // seconds, without a manual reload. The reloaded page is server-rendered
    // read-only, so the poller stops itself (it isn't emitted when closed).
    (function () {
        if (!conversationId) return;
        const tick = async () => {
            try {
                const r = await fetch('/chat/session-status?conversation=' + conversationId, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!r.ok) return;
                const s = await r.json();
                if (s && s.closed) {
                    clearInterval(timer);
                    location.reload();
                }
            } catch (e) { /* transient network error — keep polling */ }
        };
        const timer = setInterval(tick, 8000);
    })();
    <?php endif; ?>
</script>