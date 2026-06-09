<?php
/**
 * Department-admin console.
 *
 * @var array{id:int, email:string, first_name:string, last_name:string, roles:list<string>, department_id:int|null}|null $user
 * @var array{name:string, place_name:string}|null $department
 * @var list<array<string, mixed>> $pendingResearchers
 * @var list<array<string, mixed>> $pendingSpecialisations
 * @var list<array<string, mixed>> $departmentMembers
 * @var list<array<string, mixed>> $researchers
 * @var list<array<string, mixed>> $revokedResearchers
 */
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$department = $department ?? null;
$pendingResearchers = $pendingResearchers ?? [];
$pendingSpecialisations = $pendingSpecialisations ?? [];
$departmentMembers = $departmentMembers ?? [];
$researchers = $researchers ?? [];
$revokedResearchers = $revokedResearchers ?? [];
$currentUserId = (int) ($user['id'] ?? 0);
?>
<div class="page-header">
    <div class="page-header-row">
        <h1>Administration</h1>
        <?php if ($department !== null): ?>
            <span class="badge badge-teacher"><?= icon('building', '', 13) ?> <?= htmlspecialchars($department['name']) ?></span>
        <?php endif; ?>
    </div>
    <p class="page-sub">Espace d'administration de votre département.</p>
</div>

<div class="page-body">

    <div class="admin-section">
        <div class="admin-identity">
            <span class="admin-identity-icon"><?= icon('user', '', 18) ?></span>
            <div>
                <div class="admin-identity-name">
                    Connecté en tant que <strong><?= htmlspecialchars($displayName !== '' ? $displayName : 'administrateur') ?></strong>
                    <span class="badge badge-draft" style="margin-left:6px;">admin de département</span>
                </div>
                <div class="admin-identity-meta">
                    <?= icon('message-circle', '', 12) ?> <?= htmlspecialchars($user['email'] ?? '') ?>
                    <?php if ($department !== null): ?>
                        &middot; <?= icon('building', '', 12) ?> département <?= htmlspecialchars($department['name']) ?>
                        &middot; <?= icon('graduation-cap', '', 12) ?> <?= htmlspecialchars($department['place_name']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <a href="/department-admin/addModel" class="btn sm" style="margin-left:auto;">
                <?= icon('plus', '', 13) ?> ajouter un model
            </a>
            <a href="/logout" class="btn danger sm" style="margin-left:auto;">
                <?= icon('lock', '', 13) ?> Se déconnecter
            </a>
        </div>
    </div>

    <div class="admin-section" data-hide-when-empty="pending"<?= $pendingResearchers === [] ? ' hidden' : '' ?>>
        <div class="admin-pending" data-pending-list="pending">
            <h2><?= icon('alert-triangle', '', 16) ?> Demandes chercheurs en attente (<span data-count="pending"><?= count($pendingResearchers) ?></span>)</h2>
            <?php foreach ($pendingResearchers as $p): ?>
                <?php $this->renderPartial('partials/department_admin/pending_researcher_request', ['pending' => $p]); ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="admin-section" data-hide-when-empty="pending-spec"<?= $pendingSpecialisations === [] ? ' hidden' : '' ?>>
        <div class="admin-pending" data-pending-list="pending-spec">
            <h2><?= icon('alert-triangle', '', 16) ?> Demandes d'habilitation en attente (<span data-count="pending-spec"><?= count($pendingSpecialisations) ?></span>)</h2>
            <?php foreach ($pendingSpecialisations as $p): ?>
                <?php $this->renderPartial('partials/department_admin/pending_specialisation_request', ['pending' => $p]); ?>
            <?php endforeach; ?>
        </div>
    </div>

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
                        <th></th>
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

    <div class="admin-section">
        <h2><?= icon('flask-conical', '', 16) ?> Chercheurs autorisés (<span data-count="authorized"><?= count($researchers) ?></span>)</h2>
        <table class="admin-table sortable" data-researcher-table="authorized">
            <thead>
                <tr>
                    <th data-sort="text">Nom</th>
                    <th>Email</th>
                    <th>Laboratoire</th>
                    <th>Accès</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($researchers as $r): ?>
                    <?php $this->renderPartial('partials/department_admin/researcher_row', ['researcher' => $r, 'mode' => 'authorized']); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="admin-empty" data-empty="authorized"<?= $researchers === [] ? '' : ' hidden' ?>>
            Aucun chercheur autorisé sur votre département.
        </p>

        <div data-hide-when-empty="revoked"<?= $revokedResearchers === [] ? ' hidden' : '' ?>>
            <h3 class="admin-subhead"><?= icon('x-circle', '', 14) ?> Accès révoqués (<span data-count="revoked"><?= count($revokedResearchers) ?></span>)</h3>
            <table class="admin-table sortable" data-researcher-table="revoked">
                <thead>
                    <tr>
                        <th data-sort="text">Nom</th>
                        <th>Email</th>
                        <th>Laboratoire</th>
                        <th>Accès</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($revokedResearchers as $r): ?>
                        <?php $this->renderPartial('partials/department_admin/researcher_row', ['researcher' => $r, 'mode' => 'revoked']); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
