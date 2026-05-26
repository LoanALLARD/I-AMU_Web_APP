<?php
use App\Models\Session as SessionModel;

// Helper local : libellé FR + classe CSS pour le badge de statut.
$statusMeta = function (string $status): array {
    return match ($status) {
        SessionModel::STATUS_DRAFT     => ['Brouillon', 'badge-draft'],
        SessionModel::STATUS_SCHEDULED => ['Planifiée', 'badge-scheduled'],
        SessionModel::STATUS_ACTIVE    => ['En cours',  'badge-active'],
        SessionModel::STATUS_ENDED     => ['Terminée',  'badge-ended'],
        SessionModel::STATUS_CANCELLED => ['Annulée',   'badge-cancelled'],
        default                        => [$status,     'badge-default'],
    };
};

$sessionModel = new SessionModel();
?>
<div class="page-container">
    <div class="page-header">
        <h1>Mes sessions</h1>
        <a href="/sessions/create" class="btn btn-primary">+ Créer une session</a>
    </div>

    <?php if (empty($sessions)): ?>
        <div class="empty-state">
            <p>Aucune session créée pour le moment.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th>Code d'accès</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $session):
                        $computed = $sessionModel->computedStatus($session);
                        [$statusLabel, $statusClass] = $statusMeta($computed);
                        $canEdit = $sessionModel->canBeModified($session);
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($session['name']) ?></td>
                            <td><span class="badge badge-<?= strtolower($session['type']) ?>"><?= $session['type'] ?></span></td>
                            <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                            <td><code class="access-code"><?= htmlspecialchars($session['access_code']) ?></code></td>
                            <td><?= $session['starts_at'] ? date('d/m/Y H:i', strtotime($session['starts_at'])) : '-' ?></td>
                            <td><?= $session['ends_at'] ? date('d/m/Y H:i', strtotime($session['ends_at'])) : '-' ?></td>
                            <td>
                                <a href="/sessions/<?= $session['session_id'] ?>" class="btn btn-sm btn-secondary">Dashboard</a>
                                <?php if ($canEdit): ?>
                                    <a href="/sessions/<?= $session['session_id'] ?>/edit" class="btn btn-sm btn-secondary">Modifier</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
