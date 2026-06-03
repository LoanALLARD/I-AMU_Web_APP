<?php
/**
 * @var array<string, mixed> $view  Dashboard data from SessionService::dashboard()
 */
?>
<div class="dashboard-header">
    <div>
        <h1 style="margin:0;font-size:24px;font-weight:600;letter-spacing:-0.02em;">
            <?= htmlspecialchars($view['name']) ?>
        </h1>
        <div class="dashboard-meta">
            <span class="badge <?= htmlspecialchars($view['typeClass']) ?>"><?= htmlspecialchars($view['typeLabel']) ?></span>
            <span class="badge <?= htmlspecialchars($view['statusClass']) ?>"><?= htmlspecialchars($view['statusLabel']) ?></span>
            <code class="access-code-cell" style="font-size:13px;color:var(--gray-600);">
                <?= htmlspecialchars($view['accessCode']) ?>
            </code>
        </div>
    </div>
    <div class="dashboard-actions">
        <?php if ($view['canEdit']): ?>
            <a href="/sessions/<?= (int) $view['id'] ?>/edit" class="btn">
                <?= icon('edit', '', 12) ?> Modifier
            </a>
        <?php endif; ?>
        <?php if ($view['canStart']): ?>
            <form method="POST" action="/sessions/<?= (int) $view['id'] ?>/start" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn success">
                    <?= icon('play', '', 12) ?> Démarrer
                </button>
            </form>
        <?php endif; ?>
        <?php if ($view['canEnd']): ?>
            <form method="POST" action="/sessions/<?= (int) $view['id'] ?>/end" style="margin:0;"
                  onsubmit="return confirm('Terminer cette session maintenant ?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn">
                    <?= icon('square', '', 12) ?> Terminer
                </button>
            </form>
        <?php endif; ?>
        <?php if ($view['canCancel']): ?>
            <form method="POST" action="/sessions/<?= (int) $view['id'] ?>/cancel" style="margin:0;"
                  onsubmit="return confirm('Annuler cette session ?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn danger">
                    <?= icon('x-circle', '', 12) ?> Annuler
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="dashboard-grid">
    <div>
        <div class="dashboard-card">
            <h2>Planning</h2>
            <div class="kv-grid">
                <span class="kv-key">démarrage</span>
                <span class="kv-val"><?= htmlspecialchars($view['startsAtFormatted'] ?? '— non planifié') ?></span>
                <span class="kv-key">fin</span>
                <span class="kv-val"><?= htmlspecialchars($view['endsAtFormatted'] ?? '— non planifié') ?></span>
                <?php if ($view['closedAtFormatted']): ?>
                    <span class="kv-key">clôturée le</span>
                    <span class="kv-val"><?= htmlspecialchars($view['closedAtFormatted']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-card">
            <h2>Limites</h2>
            <div class="kv-grid">
                <span class="kv-key">prompt max</span>
                <span class="kv-val mono">
                    <?= $view['maxInputSize'] !== null
                        ? number_format((float) $view['maxInputSize'], 0, ',', ' ') . ' tok'
                        : 'sans limite' ?>
                </span>
            </div>
        </div>

        <?php if ($view['prePromptOverride'] !== null || $view['postPromptOverride'] !== null || $view['instructions'] !== null): ?>
            <div class="dashboard-card">
                <h2>Prompts</h2>
                <?php if ($view['prePromptOverride'] !== null): ?>
                    <p class="kv-key" style="margin: 4px 0 6px;">pré-prompt (visible étudiant)</p>
                    <div class="prompt-block"><?= htmlspecialchars($view['prePromptOverride']) ?></div>
                <?php endif; ?>
                <?php if ($view['postPromptOverride'] !== null): ?>
                    <p class="kv-key" style="margin: 14px 0 6px;">post-prompt (invisible étudiant)</p>
                    <div class="prompt-block"><?= htmlspecialchars($view['postPromptOverride']) ?></div>
                <?php endif; ?>
                <?php if ($view['instructions'] !== null): ?>
                    <p class="kv-key" style="margin: 14px 0 6px;">instructions</p>
                    <div class="prompt-block"><?= htmlspecialchars($view['instructions']) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <aside>
        <div class="dashboard-card">
            <h2>Code d'accès</h2>
            <div class="access-code-display" style="font-size: 38px; margin: 4px 0 12px;">
                <?= htmlspecialchars($view['accessCode']) ?>
            </div>
            <div class="access-card-actions">
                <button type="button" class="btn bordered" id="btn-copy-code">
                    <?= icon('copy', '', 11) ?> copier
                </button>
                <button type="button" class="btn bordered" id="btn-fullscreen-code">
                    <?= icon('eye', '', 11) ?> plein écran
                </button>
            </div>
        </div>

        <div class="dashboard-card">
            <h2>Modèles autorisés</h2>
            <?php if ($view['authorizedModels'] === []): ?>
                <p style="color:var(--gray-400);font-size:12px;">Aucun modèle autorisé.</p>
            <?php else: ?>
                <div class="preview-models">
                    <?php foreach ($view['authorizedModels'] as $m): ?>
                        <div class="preview-model">
                            <span><?= htmlspecialchars((string) $m['name']) ?></span>
                            <?php if (!empty($m['version'])): ?>
                                <span style="color:var(--gray-400);">· <?= htmlspecialchars((string) $m['version']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<script>
    window.__IAMU_SESSION_DASHBOARD__ = {
        code: <?= json_encode($view['accessCode']) ?>
    };
</script>
<?php
$jsPath = __DIR__ . '/../../../../public/assets/js/session-create.js';
$jsVer  = is_file($jsPath) ? filemtime($jsPath) : 0;
?>
<script src="/assets/js/session-create.js?v=<?= $jsVer ?>" defer></script>
