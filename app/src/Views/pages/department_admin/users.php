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
        <h2><?= icon('users', '', 16) ?> Utilisateurs du département (<?= $numberOfMembers["count"] ?? 0 ?>)</h2>
        
        <div class="search-bar-container" style="margin-bottom: 20px;">
            <div class="input-group">
                <input type="text" 
                    id="user-search-input" 
                    class="form-control" 
                    placeholder="Rechercher par nom ou prénom..." 
                    autocomplete="off"
                    style="width: 100%; max-width: 400px; padding: 10px; border-radius: 4px; border: 1px solid var(--gray-300, #ccc);">
            </div>
        </div>

        <?php if ($departmentMembers === []): ?>
            <div class="dashboard-card" id="no-members-notice">
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
                <tbody id="users-table-body">
                    <?php foreach ($departmentMembers as $m): ?>
                        <?php $this->renderPartial('partials/department_admin/member_row', ['member' => $m, 'currentUserId' => $currentUserId]); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="load-more-container" style="text-align: center; margin-top: 20px;">
                <button type="button" 
                        id="load-more-btn" 
                        class="btn btn-secondary"
                        <?= $nextCursor === null ? 'style="display:none;"' : '' ?>
                        data-next-id="<?= $nextCursor['id'] ?? '' ?>"
                        data-next-lastname="<?= htmlspecialchars($nextCursor['last_name'] ?? '') ?>"
                        data-next-firstname="<?= htmlspecialchars($nextCursor['first_name'] ?? '') ?>">
                    Charger plus d'utilisateurs
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

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