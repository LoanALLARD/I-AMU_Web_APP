<div class="page-container">
    <div class="page-header">
        <h1>Configuration</h1>
    </div>

    <div class="admin-nav">
        <a href="/admin" class="admin-nav-item">Dashboard</a>
        <a href="/admin/users" class="admin-nav-item">Utilisateurs</a>
        <a href="/admin/models" class="admin-nav-item">Modèles LLM</a>
        <a href="/admin/config" class="admin-nav-item active">Configuration</a>
    </div>

    <div class="config-sections">
        <div class="config-card">
            <h3>Application</h3>
            <div class="config-item">
                <span class="config-key">Nom</span>
                <span class="config-value"><?= htmlspecialchars($appConfig['app']['name']) ?></span>
            </div>
            <div class="config-item">
                <span class="config-key">URL</span>
                <span class="config-value"><?= htmlspecialchars($appConfig['app']['url']) ?></span>
            </div>
            <div class="config-item">
                <span class="config-key">Debug</span>
                <span class="config-value"><?= $appConfig['app']['debug'] ? icon('check', 'text-success') . ' Activé' : icon('x', 'text-error') . ' Désactivé' ?></span>
            </div>
            <div class="config-item">
                <span class="config-key">Timezone</span>
                <span class="config-value"><?= htmlspecialchars($appConfig['app']['timezone']) ?></span>
            </div>
        </div>

        <div class="config-card">
            <h3>Domaines email autorisés</h3>
            <div class="config-item">
                <span class="config-key">Étudiants</span>
                <span class="config-value"><?= htmlspecialchars(implode(', ', $appConfig['domains']['student'])) ?></span>
            </div>
            <div class="config-item">
                <span class="config-key">Enseignants</span>
                <span class="config-value"><?= htmlspecialchars(implode(', ', $appConfig['domains']['teacher'])) ?></span>
            </div>
        </div>

        <div class="config-card">
            <h3>RGPD</h3>
            <div class="config-item">
                <span class="config-key">Conservation données</span>
                <span class="config-value"><?= $appConfig['rgpd']['data_retention_days'] ?> jours</span>
            </div>
            <div class="config-item">
                <span class="config-key">Archivage conversations</span>
                <span class="config-value"><?= $appConfig['rgpd']['conversation_archive_days'] ?> jours</span>
            </div>
            <div class="config-item">
                <span class="config-key">Consentement obligatoire</span>
                <span class="config-value"><?= $appConfig['rgpd']['require_consent'] ? icon('check', 'text-success') . ' Oui' : icon('x', 'text-error') . ' Non' ?></span>
            </div>
        </div>

        <div class="config-card">
            <h3>Ollama</h3>
            <div class="config-item">
                <span class="config-key">Hôte</span>
                <span class="config-value"><code><?= htmlspecialchars($appConfig['ollama']['host']) ?></code></span>
            </div>
        </div>

        <div class="config-card">
            <h3>Sessions par défaut</h3>
            <div class="config-item">
                <span class="config-key">Taille max prompt</span>
                <span class="config-value"><?= $appConfig['session_defaults']['max_input_size'] ?> caractères</span>
            </div>
            <div class="config-item">
                <span class="config-key">Durée max examen</span>
                <span class="config-value"><?= $appConfig['session_defaults']['max_duration'] / 60 ?> minutes</span>
            </div>
        </div>
    </div>

    <p class="text-muted config-note">
        Pour modifier la configuration, éditez le fichier <code>app/config/config.php</code> 
        ou les variables d'environnement du serveur.
    </p>
</div>
