<div class="page-container">
    <div class="page-header">
        <h1>Export de données</h1>
    </div>

    <div class="form-card">
        <p>Exportez les données d'interactions au format JSON pour vos analyses de recherche.</p>

        <form method="GET" action="/export/json" class="export-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="from">Date de début</label>
                    <input type="date" id="from" name="from">
                </div>
                <div class="form-group">
                    <label for="to">Date de fin</label>
                    <input type="date" id="to" name="to">
                </div>
            </div>

            <div class="form-group">
                <label for="session_id">ID de session (optionnel)</label>
                <input type="number" id="session_id" name="session_id" placeholder="Toutes les sessions">
            </div>

            <button type="submit" class="btn btn-primary">Exporter en JSON</button>
        </form>
    </div>
</div>
