<div class="page-container">
    <div class="page-header">
        <h1>Créer une session</h1>
    </div>

    <div class="form-card">
        <form method="POST" action="/sessions/store">
            <div class="form-group">
                <label for="name">Nom de la session</label>
                <input type="text" id="name" name="name" placeholder="Ex: TP Algorithmique - Groupe A" required>
            </div>

            <div class="form-group">
                <label for="type">Type de session</label>
                <select id="type" name="type" required>
                    <option value="TP">TP / Cours</option>
                    <option value="EXAM">Examen</option>
                    <option value="SANDBOX">Sandbox (libre)</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="starts_at">Date de début</label>
                    <input type="datetime-local" id="starts_at" name="starts_at">
                </div>
                <div class="form-group">
                    <label for="ends_at">Date de fin</label>
                    <input type="datetime-local" id="ends_at" name="ends_at">
                </div>
            </div>

            <div class="form-group">
                <label>Modèles autorisés</label>
                <div class="checkbox-grid">
                    <?php foreach (($models ?? []) as $model): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="models[]" value="<?= $model['model_id'] ?>" checked>
                            <?= htmlspecialchars($model['name']) ?> (<?= htmlspecialchars($model['version'] ?? '') ?>)
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="system_prompt">Pré-prompt système (optionnel)</label>
                <textarea id="system_prompt" name="system_prompt" rows="4"
                          placeholder="Ex: Tu es un assistant pédagogique. Ne donne pas les réponses directement, guide l'étudiant."></textarea>
                <small class="form-hint">Ce prompt sera envoyé au modèle avant chaque conversation dans cette session.</small>
            </div>

            <div class="form-group">
                <label for="max_input_size">Taille max du prompt (caractères)</label>
                <input type="number" id="max_input_size" name="max_input_size" value="2000" min="100" max="10000">
            </div>

            <div class="form-group">
                <label for="instructions">Instructions pour les étudiants (optionnel)</label>
                <textarea id="instructions" name="instructions" rows="3"
                          placeholder="Instructions visibles par les étudiants au début de la session..."></textarea>
            </div>

            <div class="form-actions">
                <a href="/sessions" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary">Créer la session</button>
            </div>
        </form>
    </div>
</div>
