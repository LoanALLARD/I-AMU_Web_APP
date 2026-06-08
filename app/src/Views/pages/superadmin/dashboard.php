<div class="register-card superadmin-panel">

    <div class="card-header">
        <h1>Panel super administrateur</h1>
        <?php if (!empty($superAdmin)): ?>
            <p class="panel-greeting">
                Connecte en tant que
                <?= htmlspecialchars(trim(($superAdmin['first_name'] ?? '') . ' ' . ($superAdmin['last_name'] ?? ''))) ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="accent-bar"></div>

    <div class="panel-body">
        <p>Les fonctionnalites d'administration seront ajoutees ici.</p>

        <form method="POST" action="/super-admin/logout" class="logout-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn-submit">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Se deconnecter
            </button>
        </form>
    </div>
</div>
