<div class="superadmin-dashboard">

    <?php require __DIR__ . '/_header.php'; ?>

    <main class="dashboard-content">

        <?php require __DIR__ . '/_flash.php'; ?>

        <section class="panel-section">
            <h2>Inviter un administrateur de departement</h2>
            <p class="section-lead">
                Un lien d'activation valable 7 jours est envoye a l'adresse indiquee.
                Le destinataire choisit son mot de passe pour creer son compte.
            </p>

            <form method="POST" action="/super-admin/department-admins/invite" class="domain-form">
                <?= csrf_field() ?>
                <div class="domain-form-row">
                    <div class="form-group">
                        <label for="email">Adresse email</label>
                        <input type="email" id="email" name="email"
                               placeholder="prenom.nom@univ-amu.fr" required>
                    </div>
                    <div class="form-group">
                        <label for="department_id">Departement</label>
                        <select id="department_id" name="department_id" required>
                            <option value="">Choisir un departement...</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit">
                        <?= icon('plus', '', 16) ?>
                        Envoyer l'invitation
                    </button>
                </div>
            </form>
        </section>
    </main>
</div>