<div class="error-container">
    <div class="error-card">
        <h1 class="error-code"><?= $code ?? 404 ?></h1>
        <p class="error-message"><?= htmlspecialchars($message ?? 'Page introuvable') ?></p>
        <a href="/" class="btn btn-primary">Retour à l'accueil</a>
    </div>
</div>
