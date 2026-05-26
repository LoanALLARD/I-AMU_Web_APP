<div class="page-container">
    <div class="page-header">
        <h1>Gestion des modèles LLM</h1>
    </div>

    <div class="admin-nav">
        <a href="/admin" class="admin-nav-item">Dashboard</a>
        <a href="/admin/users" class="admin-nav-item">Utilisateurs</a>
        <a href="/admin/models" class="admin-nav-item active">Modèles LLM</a>
        <a href="/admin/config" class="admin-nav-item">Configuration</a>
    </div>

    <!-- Statut Ollama + bouton de synchronisation -->
    <div class="alert <?= $ollamaAvailable ? 'flash-success' : 'alert-error' ?>" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;">
        <span>
            <?php if ($ollamaAvailable): ?>
                <?= icon('check', 'text-success') ?> Ollama est connecté — <?= count($ollamaModels) ?> modèle(s) disponible(s) sur le serveur.
            <?php else: ?>
                <?= icon('alert-triangle', 'text-warning') ?> Ollama n'est pas accessible. Vérifiez que le service est démarré.
            <?php endif; ?>
        </span>
        <?php if ($ollamaAvailable): ?>
            <form method="POST" action="/admin/models/sync" style="margin:0;">
                <button type="submit" class="btn btn-sm btn-primary"><?= icon('refresh-cw') ?> Synchroniser avec Ollama</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Modèles existants -->
    <h2>Modèles enregistrés</h2>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Version</th>
                    <th>Provider</th>
                    <th>Max Tokens</th>
                    <th>Context Window</th>
                    <th>Actif</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($models as $model): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($model['name']) ?></strong></td>
                        <td><?= htmlspecialchars($model['version'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($model['provider']) ?></td>
                        <td><?= number_format($model['max_tokens'] ?? 0) ?></td>
                        <td><?= number_format($model['context_window'] ?? 0) ?></td>
                        <td>
                            <button class="btn btn-sm <?= $model['is_active'] ? 'btn-primary' : 'btn-secondary' ?>"
                                    onclick="toggleModel(<?= $model['model_id'] ?>, <?= $model['is_active'] ? 'false' : 'true' ?>)">
                                <?= $model['is_active'] ? icon('check') . ' Actif' : 'Inactif' ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Ajouter un modèle -->
    <h2>Ajouter un modèle</h2>
    <div class="form-card">
        <form method="POST" action="/admin/models/store">
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Nom du modèle</label>
                    <input type="text" id="name" name="name" placeholder="ex: Mistral" required>
                </div>
                <div class="form-group">
                    <label for="version">Version</label>
                    <input type="text" id="version" name="version" placeholder="ex: 7B">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="max_tokens">Max Tokens</label>
                    <input type="number" id="max_tokens" name="max_tokens" value="4096">
                </div>
                <div class="form-group">
                    <label for="context_window">Context Window</label>
                    <input type="number" id="context_window" name="context_window" value="8192">
                </div>
            </div>
            <input type="hidden" name="provider" value="ollama">
            <input type="hidden" name="is_active" value="1">
            <button type="submit" class="btn btn-primary">Ajouter le modèle</button>
        </form>
    </div>
</div>

<script>
async function toggleModel(modelId, isActive) {
    try {
        const response = await fetch('/admin/models/toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ model_id: modelId, is_active: isActive ? '1' : '0' }),
        });
        if (!response.ok) {
            const text = await response.text();
            console.error('Réponse serveur:', text);
            alert('Erreur serveur (HTTP ' + response.status + '). Voir la console.');
            return;
        }
        const data = await response.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Erreur lors de la mise à jour.');
        }
    } catch (err) {
        console.error('Erreur:', err);
        alert('Erreur réseau : ' + err.message);
    }
}
</script>
