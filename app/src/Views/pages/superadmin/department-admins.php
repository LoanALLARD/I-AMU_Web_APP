<?php
/**
 * @var list<array{id:int, name:string}> $places
 * @var list<array{id:int, email:string, first_name:?string, last_name:?string, is_active:bool, department_name:?string, place_name:?string}> $departmentAdmins
 */
?>
<div class="superadmin-dashboard">

    <?php require __DIR__ . '/_header.php'; ?>

    <main class="dashboard-content">

        <?php require __DIR__ . '/_flash.php'; ?>

        <nav class="admin-tabs" role="tablist">
            <button type="button" class="admin-tab is-active" data-tab="invite-dept" role="tab" aria-selected="true">
                <?= icon('user', '', 16) ?>
                Inviter un admin de departement
            </button>
            <button type="button" class="admin-tab" data-tab="manage" role="tab" aria-selected="false">
                <?= icon('users', '', 16) ?>
                Gerer les admins
            </button>
            <button type="button" class="admin-tab" data-tab="invite-super" role="tab" aria-selected="false">
                <?= icon('key-round', '', 16) ?>
                Inviter un super admin
            </button>
        </nav>

        <section class="panel-section admin-tab-panel" data-panel="invite-dept">
            <h2>Inviter un administrateur de departement</h2>
            <p class="section-lead">
                Un lien d'activation valable 7 jours est envoye a l'adresse indiquee.
                Le destinataire choisit son mot de passe pour creer son compte.
            </p>

            <form method="POST" action="/super-admin/department-admins/invite" class="domain-form">
                <?= csrf_field() ?>
                <div class="domain-form-row">
                    <div class="form-group">
                        <label for="invite-email">Adresse email</label>
                        <input type="email" id="invite-email" name="email"
                               placeholder="prenom.nom@univ-amu.fr" required>
                    </div>
                    <div class="form-group">
                        <label for="invite-place">Site</label>
                        <select id="invite-place" name="place_id" required>
                            <option value="">Choisir un site...</option>
                            <?php foreach ($places as $place): ?>
                                <option value="<?= (int) $place['id'] ?>"><?= htmlspecialchars($place['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="invite-department">Departement</label>
                        <select id="invite-department" name="department_id" required disabled>
                            <option value="">Choisir d'abord un site...</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit">
                        <?= icon('send', '', 16) ?>
                        Envoyer l'invitation
                    </button>
                </div>
            </form>
        </section>

        <section class="panel-section admin-tab-panel" data-panel="manage" hidden>
            <h2>Administrateurs de departement</h2>
            <p class="section-lead">
                Revoquer un acces desactive le compte : l'administrateur ne peut plus
                se connecter. L'action est reversible via le bouton Reactiver.
            </p>

            <?php if (empty($departmentAdmins)): ?>
                <p class="section-empty">Aucun administrateur de departement pour le moment.</p>
            <?php else: ?>
                <table class="domain-table sortable">
                    <thead>
                    <tr>
                        <th data-sort="text">Nom</th>
                        <th data-sort="text">Email</th>
                        <th data-sort="text">Departement</th>
                        <th class="col-state" data-sort="text">Etat</th>
                        <th class="col-action">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($departmentAdmins as $admin): ?>
                        <?php
                        $fullName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
                        if ($fullName === '') {
                            $fullName = '-';
                        }
                        $scope = $admin['department_name'] !== null
                            ? $admin['department_name'] . ' (' . ($admin['place_name'] ?? '?') . ')'
                            : '-';
                        ?>
                        <tr class="<?= $admin['is_active'] ? '' : 'is-inactive' ?>">
                            <td class="cell-domain" data-label="Nom"><?= htmlspecialchars($fullName) ?></td>
                            <td data-label="Email"><?= htmlspecialchars($admin['email']) ?></td>
                            <td data-label="Departement"><?= htmlspecialchars($scope) ?></td>
                            <td data-label="Etat" data-sort-value="<?= $admin['is_active'] ? '1' : '0' ?>">
                                <?php if ($admin['is_active']): ?>
                                    <span class="badge-state badge-active">Actif</span>
                                <?php else: ?>
                                    <span class="badge-state badge-inactive">Revoque</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-action" data-label="Action">
                                <?php if ($admin['is_active']): ?>
                                    <form method="POST" action="/super-admin/department-admins/revoke">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $admin['id'] ?>">
                                        <button type="submit" class="btn-row btn-row-danger">
                                            <?= icon('x', '', 15) ?> Revoquer
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="panel-section admin-tab-panel" data-panel="invite-super" hidden>
            <h2>Inviter un super administrateur</h2>
            <p class="section-lead">
                Le super administrateur dispose de tous les droits sur la plateforme.
                Un lien d'activation valable 7 jours est envoye a l'adresse indiquee.
            </p>

            <form method="POST" action="/super-admin/super-admins/invite" class="domain-form">
                <?= csrf_field() ?>
                <div class="domain-form-row">
                    <div class="form-group">
                        <label for="invite-super-email">Adresse email</label>
                        <input type="email" id="invite-super-email" name="email"
                               placeholder="prenom.nom@univ-amu.fr" required>
                    </div>
                    <button type="submit" class="btn-submit">
                        <?= icon('send', '', 16) ?>
                        Envoyer l'invitation
                    </button>
                </div>
            </form>
        </section>
    </main>
</div>

<?php
$inviteJs  = __DIR__ . '/../../../../public/assets/js/admin-invite.js';
$inviteVer = is_file($inviteJs) ? filemtime($inviteJs) : 0;
?>
<script src="/assets/js/admin-invite.js?v=<?= $inviteVer ?>" defer></script>