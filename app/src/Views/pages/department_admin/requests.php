<?php
/**
 * Department-admin console — "Requests" tab: pending requests plus the
 * habilitated-teacher and authorized-researcher lists (with their revoked ones).
 *
 * @var array<string, mixed>|null $user
 * @var array{name:string, place_name:string}|null $department
 * @var list<array<string, mixed>> $pendingResearchers
 * @var list<array<string, mixed>> $pendingSpecialisations
 * @var list<array<string, mixed>> $habilitatedTeachers
 * @var list<array<string, mixed>> $revokedTeachers
 * @var list<array<string, mixed>> $researchers
 * @var list<array<string, mixed>> $revokedResearchers
 */
$pendingResearchers = $pendingResearchers ?? [];
$pendingSpecialisations = $pendingSpecialisations ?? [];
$habilitatedTeachers = $habilitatedTeachers ?? [];
$revokedTeachers = $revokedTeachers ?? [];
$researchers = $researchers ?? [];
$revokedResearchers = $revokedResearchers ?? [];
$activeNav = 'requests';
?>
<div class="page-body">

    <?php $this->renderPartial('partials/department_admin/_header', [
        'user' => $user, 'department' => $department ?? null, 'activeNav' => $activeNav,
    ]); ?>

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

    <p class="admin-empty" data-empty-any="pending pending-spec"<?= ($pendingResearchers === [] && $pendingSpecialisations === []) ? '' : ' hidden' ?>>
        Aucune demande en attente.
    </p>

    <div class="admin-section">
        <h2><?= icon('graduation-cap', '', 16) ?> Enseignants habilités (<span data-count="spec-habilitated"><?= count($habilitatedTeachers) ?></span>)</h2>
        <table class="admin-table sortable" data-list-table="spec-habilitated">
            <thead>
                <tr>
                    <th data-sort="text">Nom</th>
                    <th>Email</th>
                    <th>Habilitation</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($habilitatedTeachers as $t): ?>
                    <?php $this->renderPartial('partials/department_admin/specialised_teacher_row', ['teacher' => $t, 'mode' => 'habilitated']); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="admin-empty" data-empty="spec-habilitated"<?= $habilitatedTeachers === [] ? '' : ' hidden' ?>>
            Aucun enseignant habilité dans votre département.
        </p>

        <div data-hide-when-empty="spec-revoked"<?= $revokedTeachers === [] ? ' hidden' : '' ?>>
            <h3 class="admin-subhead"><?= icon('x-circle', '', 14) ?> Habilitations révoquées (<span data-count="spec-revoked"><?= count($revokedTeachers) ?></span>)</h3>
            <table class="admin-table sortable" data-list-table="spec-revoked">
                <thead>
                    <tr>
                        <th data-sort="text">Nom</th>
                        <th>Email</th>
                        <th>Habilitation</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($revokedTeachers as $t): ?>
                        <?php $this->renderPartial('partials/department_admin/specialised_teacher_row', ['teacher' => $t, 'mode' => 'revoked']); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
