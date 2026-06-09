<?php
/**
 * Department-admin console — "Users" tab: department members.
 *
 * @var array<string, mixed>|null $user
 * @var array{name:string, place_name:string}|null $department
 * @var list<array<string, mixed>> $departmentMembers
 */
$departmentMembers = $departmentMembers ?? [];
$currentUserId = (int) ($user['id'] ?? 0);
$activeNav = 'users';
?>
<div class="page-body">

    <?php $this->renderPartial('partials/department_admin/_header', [
        'user' => $user, 'department' => $department ?? null, 'activeNav' => $activeNav,
    ]); ?>

    <div class="admin-section">
        <h2><?= icon('users', '', 16) ?> Utilisateurs du département (<?= count($departmentMembers) ?>)</h2>
        <?php if ($departmentMembers === []): ?>
            <div class="dashboard-card">
                <p style="color:var(--gray-400);font-size:13px;margin:0;">
                    Aucun enseignant ni étudiant rattaché à votre département.
                </p>
            </div>
        <?php else: ?>
            <table class="admin-table sortable">
                <thead>
                    <tr>
                        <th data-sort="text">Nom</th>
                        <th>Email</th>
                        <th data-sort="text">Rôle</th>
                        <th data-sort="text">Statut</th>
                        <th data-sort="text">Dernière connexion</th>
                        <th>Infos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departmentMembers as $m): ?>
                        <?php $this->renderPartial('partials/department_admin/member_row', ['member' => $m, 'currentUserId' => $currentUserId]); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<!-- Shared member-info modal; filled from the clicked row's <template>. -->
<div class="modal-overlay" id="member-modal">
    <div class="modal-box">
        <div class="modal-head">
            <h2 id="member-modal-title">Informations</h2>
            <button type="button" class="modal-close" id="member-modal-close" aria-label="Fermer">
                <?= icon('x', '', 16) ?>
            </button>
        </div>
        <div id="member-modal-body"></div>
    </div>
</div>

<?php
$sortJs    = __DIR__ . '/../../../../public/assets/js/table-sort.js';
$actionsJs = __DIR__ . '/../../../../public/assets/js/department_admin-actions.js';
$sortVer    = is_file($sortJs) ? filemtime($sortJs) : 0;
$actionsVer = is_file($actionsJs) ? filemtime($actionsJs) : 0;
?>
<script src="/assets/js/table-sort.js?v=<?= $sortVer ?>" defer></script>
<script src="/assets/js/department_admin-actions.js?v=<?= $actionsVer ?>" defer></script>
