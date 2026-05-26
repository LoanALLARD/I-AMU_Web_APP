<div class="chat-layout">
    <!-- Sidebar : conversations -->
    <aside class="chat-sidebar">
        <div class="sidebar-header">
            <h2>Conversations</h2>
            <button class="btn btn-sm btn-primary" id="btn-new-conversation">+ Nouvelle</button>
        </div>

        <!-- Rejoindre une session -->
        <div class="sidebar-join">
            <form method="POST" action="/sessions/join" class="join-form">
                <input type="text" name="access_code" placeholder="Code d'accès..." 
                       maxlength="6" class="input-code">
                <button type="submit" class="btn btn-sm btn-secondary">Rejoindre</button>
            </form>
        </div>

        <div class="sidebar-conversations" id="conversation-list">
            <?php foreach (($conversations ?? []) as $conv): ?>
                <a href="/chat/<?= $conv['conversation_id'] ?>" 
                   class="conversation-item <?= isset($currentConversation) && $currentConversation['conversation_id'] === $conv['conversation_id'] ? 'active' : '' ?>">
                    <span class="conv-name"><?= htmlspecialchars($conv['name']) ?></span>
                    <span class="conv-type badge badge-<?= strtolower($conv['type']) ?>">
                        <?= $conv['type'] ?>
                    </span>
                </a>
            <?php endforeach; ?>
            <?php if (empty($conversations)): ?>
                <p class="sidebar-empty">Aucune conversation. Créez-en une !</p>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Zone de chat principale -->
    <section class="chat-main">
        <?php if (isset($currentConversation)): ?>
            <div class="chat-header">
                <h2><?= htmlspecialchars($currentConversation['name']) ?></h2>
                <div class="chat-header-actions">
                    <select id="model-select" class="select-model">
                        <?php foreach (($models ?? []) as $model): ?>
                            <option value="<?= $model['model_id'] ?>">
                                <?= htmlspecialchars($model['name']) ?> (<?= htmlspecialchars($model['version'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Messages -->
            <div class="chat-messages" id="chat-messages">
                <?php foreach (($interactions ?? []) as $interaction): ?>
                    <div class="message message-user">
                        <div class="message-content"><?= nl2br(htmlspecialchars($interaction['prompt'])) ?></div>
                    </div>
                    <?php if ($interaction['response']): ?>
                        <div class="message message-ai">
                            <div class="message-content" data-raw="<?= htmlspecialchars($interaction['response']) ?>"><?= nl2br(htmlspecialchars($interaction['response'])) ?></div>
                            <div class="message-meta">
                                <span class="meta-latency"><?= $interaction['latency'] ?>ms</span>
                                <span class="meta-tokens"><?= $interaction['input_tokens'] + $interaction['output_tokens'] ?> tokens</span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Zone de saisie -->
            <div class="chat-input-area">
                <form id="chat-form" class="chat-form">
                    <input type="hidden" name="conversation_id" value="<?= $currentConversation['conversation_id'] ?>">
                    <textarea name="prompt" id="prompt-input" placeholder="Écrivez votre message..."
                              rows="1" required></textarea>
                    <button type="submit" class="btn btn-primary btn-send" id="btn-send">
                        Envoyer
                    </button>
                </form>
            </div>

        <?php else: ?>
            <!-- État vide : pas de conversation sélectionnée -->
            <div class="chat-empty">
                <div class="empty-icon"><?= icon('message-circle', 'icon-xl') ?></div>
                <h2>Bienvenue sur I-AMU</h2>
                <p>Créez une nouvelle conversation ou rejoignez une session via un code d'accès.</p>
                <button class="btn btn-primary" id="btn-start-conversation">
                    Nouvelle conversation
                </button>
            </div>
        <?php endif; ?>
    </section>
</div>
