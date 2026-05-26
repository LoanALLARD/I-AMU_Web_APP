<?php
use App\Models\Session as SessionModel;

$sessionModel = new SessionModel();
$computed = $sessionModel->computedStatus($session);
$actions  = $sessionModel->availableActions($session);
$sid      = (int) $session['session_id'];

$statusMeta = match ($computed) {
    SessionModel::STATUS_DRAFT     => ['Brouillon', 'badge-draft'],
    SessionModel::STATUS_SCHEDULED => ['Planifiée', 'badge-scheduled'],
    SessionModel::STATUS_ACTIVE    => ['En cours',  'badge-active'],
    SessionModel::STATUS_ENDED     => ['Terminée',  'badge-ended'],
    SessionModel::STATUS_CANCELLED => ['Annulée',   'badge-cancelled'],
    default                        => [$computed,   'badge-default'],
};
[$statusLabel, $statusClass] = $statusMeta;
?>
<div class="page-container">
    <div class="page-header session-dashboard-header">
        <div>
            <h1><?= htmlspecialchars($session['name']) ?></h1>
            <div class="header-info">
                <span class="badge badge-<?= strtolower($session['type']) ?>"><?= $session['type'] ?></span>
                <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                <code class="access-code"><?= htmlspecialchars($session['access_code']) ?></code>
            </div>
        </div>

        <!-- ─── Actions selon le statut ───────────────────────────── -->
        <div class="dashboard-actions">
            <?php if ($actions['can_edit']): ?>
                <a href="/sessions/<?= $sid ?>/edit" class="btn btn-secondary btn-sm">
                    <?= icon('edit') ?> Modifier
                </a>
            <?php endif; ?>

            <?php if ($actions['can_start']): ?>
                <form method="POST" action="/sessions/<?= $sid ?>/start" style="display:inline;margin:0;">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <?= icon('play') ?> Démarrer
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($computed === SessionModel::STATUS_ACTIVE): ?>
                <a href="/exam/<?= $sid ?>/supervise" class="btn btn-primary btn-sm">
                    <?= icon('eye') ?> Superviser
                </a>
            <?php endif; ?>

            <?php if ($actions['can_end']): ?>
                <form method="POST" action="/sessions/<?= $sid ?>/end" style="display:inline;margin:0;"
                      onsubmit="return confirm('Terminer cette session maintenant ?')">
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <?= icon('square') ?> Terminer
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($actions['can_cancel']): ?>
                <form method="POST" action="/sessions/<?= $sid ?>/cancel" style="display:inline;margin:0;"
                      onsubmit="return confirm('Annuler définitivement cette session ?')">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <?= icon('x-circle') ?> Annuler
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats globales -->
    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-value"><?= count($conversations) ?></div>
            <div class="stat-label">Étudiants connectés</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($models) ?></div>
            <div class="stat-label">Modèles autorisés</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                <?php
                $totalPrompts = 0;
                // Comptage simplifié
                echo $totalPrompts;
                ?>
            </div>
            <div class="stat-label">Prompts envoyés</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                <?php
                if ($session['starts_at'] && $session['ends_at']) {
                    $dur = max(0, (strtotime($session['ends_at']) - strtotime($session['starts_at'])) / 60);
                    echo (int) $dur . ' min';
                } else {
                    echo '—';
                }
                ?>
            </div>
            <div class="stat-label">Durée prévue</div>
        </div>
    </div>

    <h2>Conversations actives</h2>
    <?php if (empty($conversations)): ?>
        <p class="text-muted">Aucun étudiant n'a rejoint cette session pour le moment.</p>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Étudiant</th>
                        <th>Conversation</th>
                        <th>Créée le</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conversations as $conv): ?>
                        <tr>
                            <td>Utilisateur #<?= $conv['user_id'] ?></td>
                            <td><?= htmlspecialchars($conv['name']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($conv['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
