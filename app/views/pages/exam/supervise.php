<div class="page-container">
    <div class="page-header">
        <h1>Supervision - <?= htmlspecialchars($session['name']) ?></h1>
        <div class="header-info">
            <span class="exam-badge">EXAMEN EN COURS</span>
            <code class="access-code"><?= htmlspecialchars($session['access_code']) ?></code>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-value"><?= count($conversations) ?></div>
            <div class="stat-label">Étudiants connectés</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($allInteractions) ?></div>
            <div class="stat-label">Prompts envoyés</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                <?php
                $remaining = max(0, strtotime($session['ends_at']) - time());
                $hours = floor($remaining / 3600);
                $mins = floor(($remaining % 3600) / 60);
                echo sprintf('%dh%02d', $hours, $mins);
                ?>
            </div>
            <div class="stat-label">Temps restant</div>
        </div>
    </div>

    <!-- Liste des étudiants connectés -->
    <div class="supervision-section">
        <h2>Étudiants</h2>
        <div class="student-grid">
            <?php foreach ($conversations as $conv): ?>
                <div class="student-card">
                    <div class="student-name">Étudiant #<?= $conv['user_id'] ?></div>
                    <div class="student-meta"><?= date('H:i', strtotime($conv['created_at'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Flux des prompts en temps réel -->
    <div class="supervision-section">
        <h2>Flux des prompts <span class="live-indicator">● EN DIRECT</span></h2>
        <div class="prompt-feed" id="prompt-feed" data-session-id="<?= $session['session_id'] ?>">
            <?php if (empty($allInteractions)): ?>
                <p class="text-muted">Aucun prompt pour le moment...</p>
            <?php endif; ?>
            <?php foreach ($allInteractions as $interaction): ?>
                <div class="prompt-entry" data-id="<?= $interaction['prompt_id'] ?>">
                    <div class="prompt-header">
                        <span class="prompt-student">Étudiant #<?= $interaction['student_user_id'] ?></span>
                        <span class="prompt-time"><?= date('H:i:s', strtotime($interaction['sent_at'])) ?></span>
                    </div>
                    <div class="prompt-text">
                        <strong>Prompt :</strong> <?= htmlspecialchars(mb_substr($interaction['prompt'], 0, 300)) ?>
                        <?= mb_strlen($interaction['prompt']) > 300 ? '...' : '' ?>
                    </div>
                    <?php if ($interaction['response']): ?>
                        <div class="prompt-response">
                            <strong>Réponse :</strong> <?= htmlspecialchars(mb_substr($interaction['response'], 0, 200)) ?>
                            <?= mb_strlen($interaction['response']) > 200 ? '...' : '' ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
