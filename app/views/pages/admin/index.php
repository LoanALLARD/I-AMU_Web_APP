<div class="page-container">
    <div class="page-header">
        <h1>Administration I-AMU</h1>
    </div>

    <div class="admin-nav">
        <a href="/admin" class="admin-nav-item active">Dashboard</a>
        <a href="/admin/users" class="admin-nav-item">Utilisateurs</a>
        <a href="/admin/models" class="admin-nav-item">Modèles LLM</a>
        <a href="/admin/config" class="admin-nav-item">Configuration</a>
    </div>

    <div class="dashboard-grid dashboard-grid-4">
        <div class="stat-card">
            <div class="stat-value"><?= $stats['total_users'] ?></div>
            <div class="stat-label">Utilisateurs</div>
        </div>
        <div class="stat-card stat-card-blue">
            <div class="stat-value"><?= $stats['total_students'] ?></div>
            <div class="stat-label">Étudiants</div>
        </div>
        <div class="stat-card stat-card-green">
            <div class="stat-value"><?= $stats['total_teachers'] ?></div>
            <div class="stat-label">Enseignants</div>
        </div>
        <div class="stat-card stat-card-purple">
            <div class="stat-value"><?= $stats['total_researchers'] ?></div>
            <div class="stat-label">Chercheurs</div>
        </div>
    </div>

    <div class="dashboard-grid dashboard-grid-3">
        <div class="stat-card">
            <div class="stat-value"><?= $stats['total_sessions'] ?></div>
            <div class="stat-label">Sessions créées</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['total_conversations'] ?></div>
            <div class="stat-label">Conversations</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['total_interactions'] ?></div>
            <div class="stat-label">Interactions (prompts)</div>
        </div>
    </div>
</div>
