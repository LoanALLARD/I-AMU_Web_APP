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

/**
 * Rend les boutons d'action d'une session.
 * Réutilisé pour la table (desktop) et le menu (mobile).
 */
$renderActions = function (int $sid, array $actions, bool $small = true) {
    $btnClass = $small ? 'icon-btn' : 'icon-btn icon-btn-lg';
    ob_start(); ?>
        <a href="/sessions/<?= $sid ?>" class="<?= $btnClass ?>" title="Voir le dashboard" aria-label="Voir le dashboard">
            <?= icon('eye') ?>
        </a>
        <?php if ($actions['can_edit']): ?>
            <a href="/sessions/<?= $sid ?>/edit" class="<?= $btnClass ?>" title="Modifier" aria-label="Modifier">
                <?= icon('edit') ?>
            </a>
        <?php endif; ?>
        <?php if ($actions['can_start']): ?>
            <form method="POST" action="/sessions/<?= $sid ?>/start" style="display:inline;margin:0;">
                <button type="submit" class="<?= $btnClass ?> icon-btn-success" title="Démarrer" aria-label="Démarrer">
                    <?= icon('play') ?>
                </button>
            </form>
        <?php endif; ?>
        <?php if ($actions['can_end']): ?>
            <form method="POST" action="/sessions/<?= $sid ?>/end" style="display:inline;margin:0;"
                  onsubmit="return confirm('Terminer cette session maintenant ?')">
                <button type="submit" class="<?= $btnClass ?> icon-btn-warning" title="Terminer" aria-label="Terminer">
                    <?= icon('square') ?>
                </button>
            </form>
        <?php endif; ?>
        <?php if ($actions['can_cancel']): ?>
            <form method="POST" action="/sessions/<?= $sid ?>/cancel" style="display:inline;margin:0;"
                  onsubmit="return confirm('Annuler cette session ?')">
                <button type="submit" class="<?= $btnClass ?> icon-btn-danger" title="Annuler" aria-label="Annuler">
                    <?= icon('x-circle') ?>
                </button>
            </form>
        <?php endif; ?>
    <?php
    return ob_get_clean();
};
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

        <!-- ═══ Vue desktop : table ════════════════════════════════ -->
        <div class="table-container session-table-desktop">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th>Code d'accès</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th class="th-actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $session):
                        $computed = $sessionModel->computedStatus($session);
                        [$statusLabel, $statusClass] = $statusMeta($computed);
                        $actions = $sessionModel->availableActions($session);
                        $sid = (int) $session['session_id'];
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($session['name']) ?></td>
                            <td><span class="badge badge-<?= strtolower($session['type']) ?>"><?= $session['type'] ?></span></td>
                            <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                            <td><code class="access-code"><?= htmlspecialchars($session['access_code']) ?></code></td>
                            <td><?= $session['starts_at'] ? date('d/m/Y H:i', strtotime($session['starts_at'])) : '-' ?></td>
                            <td><?= $session['ends_at'] ? date('d/m/Y H:i', strtotime($session['ends_at'])) : '-' ?></td>
                            <td class="cell-actions">
                                <?= $renderActions($sid, $actions) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ═══ Vue mobile : cartes ════════════════════════════════ -->
        <div class="session-cards-mobile">
            <?php foreach ($sessions as $session):
                $computed = $sessionModel->computedStatus($session);
                [$statusLabel, $statusClass] = $statusMeta($computed);
                $actions = $sessionModel->availableActions($session);
                $sid = (int) $session['session_id'];
            ?>
                <article class="session-card">
                    <header class="session-card-head">
                        <div class="session-card-title">
                            <h3><?= htmlspecialchars($session['name']) ?></h3>
                            <div class="session-card-badges">
                                <span class="badge badge-<?= strtolower($session['type']) ?>"><?= $session['type'] ?></span>
                                <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                            </div>
                        </div>
                        <!-- Menu 3 points (details/summary natif, no JS) -->
                        <details class="card-menu" data-card-menu>
                            <summary class="card-menu-trigger" aria-label="Actions">
                                <?= icon('more-horizontal') ?>
                            </summary>
                            <div class="card-menu-content">
                                <?= $renderActions($sid, $actions, false) ?>
                            </div>
                        </details>
                    </header>

                    <dl class="session-card-meta">
                        <div>
                            <dt>Code</dt>
                            <dd><code class="access-code"><?= htmlspecialchars($session['access_code']) ?></code></dd>
                        </div>
                        <div>
                            <dt>Début</dt>
                            <dd><?= $session['starts_at'] ? date('d/m/Y H:i', strtotime($session['starts_at'])) : '—' ?></dd>
                        </div>
                        <div>
                            <dt>Fin</dt>
                            <dd><?= $session['ends_at'] ? date('d/m/Y H:i', strtotime($session['ends_at'])) : '—' ?></dd>
                        </div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>

<script>
// Ferme tout autre menu 3-points quand on en ouvre un (un seul à la fois)
document.querySelectorAll('[data-card-menu]').forEach(menu => {
    menu.addEventListener('toggle', (e) => {
        if (menu.open) {
            document.querySelectorAll('[data-card-menu]').forEach(m => {
                if (m !== menu) m.removeAttribute('open');
            });
        }
    });
});
// Click en dehors → ferme
document.addEventListener('click', (e) => {
    document.querySelectorAll('[data-card-menu][open]').forEach(menu => {
        if (!menu.contains(e.target)) menu.removeAttribute('open');
    });
});
</script>
