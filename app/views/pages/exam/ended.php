<div class="exam-ended">
    <div class="ended-card">
        <div class="ended-icon"><?= icon('check', 'icon-xl text-success') ?></div>
        <h1>Examen terminé</h1>
        <p>L'examen <strong><?= htmlspecialchars($session['name']) ?></strong> est terminé.</p>
        <p>Vos réponses ont été enregistrées.</p>
        <a href="/chat" class="btn btn-primary">Retour au mode libre</a>
    </div>
</div>
