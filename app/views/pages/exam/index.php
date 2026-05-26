<?php if (!empty($instructions)): ?>
<div class="exam-instructions">
    <strong>Instructions :</strong>
    <?= nl2br(htmlspecialchars($instructions)) ?>
</div>
<?php endif; ?>

<div class="exam-chat-layout">
    <div class="chat-main">
        <div class="chat-header">
            <div class="chat-header-actions">
                <label for="model-select">Modèle :</label>
                <select id="model-select" class="select-model">
                    <?php foreach (($models ?? []) as $model): ?>
                        <option value="<?= $model['model_id'] ?>">
                            <?= htmlspecialchars($model['name']) ?> (<?= htmlspecialchars($model['version'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="char-counter">
                <span id="char-count">0</span> / <?= $maxInputSize ?>
            </div>
        </div>

        <div class="chat-messages" id="chat-messages">
            <?php foreach (($interactions ?? []) as $interaction): ?>
                <div class="message message-user">
                    <div class="message-content"><?= nl2br(htmlspecialchars($interaction['prompt'])) ?></div>
                </div>
                <?php if ($interaction['response']): ?>
                    <div class="message message-ai">
                        <div class="message-content"><?= nl2br(htmlspecialchars($interaction['response'])) ?></div>
                        <div class="message-meta">
                            <span class="meta-latency"><?= $interaction['latency'] ?>ms</span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="chat-input-area">
            <form id="exam-chat-form" class="chat-form">
                <input type="hidden" name="conversation_id" value="<?= $conversation['conversation_id'] ?>">
                <textarea name="prompt" id="prompt-input"
                          placeholder="Écrivez votre question..."
                          rows="2" required
                          maxlength="<?= $maxInputSize ?>"
                          data-max="<?= $maxInputSize ?>"></textarea>
                <button type="submit" class="btn btn-primary btn-send" id="btn-send">
                    Envoyer
                </button>
            </form>
        </div>
    </div>
</div>
