<div class="page-container">
    <div class="page-header">
        <h1>Dashboard - <?= htmlspecialchars($session['name']) ?></h1>
        <div class="header-info">
            <span class="badge badge-<?= strtolower($session['type']) ?>"><?= $session['type'] ?></span>
            <code class="access-code"><?= htmlspecialchars($session['access_code']) ?></code>
        </div>
    </div>

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
