<?php
/**
 * Department-admin console.
 *
 * @var array{id:int, email:string, first_name:string, last_name:string, roles:list<string>, department_id:int|null}|null $user
 * @var array{name:string, place_name:string}|null $department
 * @var list<array<string, mixed>> $pendingResearchers
 */
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$department = $department ?? null;
$pendingResearchers = $pendingResearchers ?? [];
?>
<div class="page-header">
    <div class="page-header-row">
        <h1>Administration</h1>
        <?php if ($department !== null): ?>
            <span class="badge badge-teacher"><?= icon('building', '', 13) ?> <?= htmlspecialchars($department['name']) ?></span>
        <?php endif; ?>
    </div>
    <p class="page-sub">Espace d'administration de votre departement.</p>
</div>

<div class="page-body">

    <div class="admin-section">
        <div class="admin-identity">
            <span class="admin-identity-icon"><?= icon('user', '', 18) ?></span>
            <div>
                <div class="admin-identity-name">
                    Connecte en tant que <strong><?= htmlspecialchars($displayName !== '' ? $displayName : 'administrateur') ?></strong>
                    <span class="badge badge-draft" style="margin-left:6px;">admin de departement</span>
                </div>
                <div class="admin-identity-meta">
                    <?= icon('message-circle', '', 12) ?> <?= htmlspecialchars($user['email'] ?? '') ?>
                    <?php if ($department !== null): ?>
                        &middot; <?= icon('building', '', 12) ?> departement <?= htmlspecialchars($department['name']) ?>
                        &middot; <?= icon('graduation-cap', '', 12) ?> <?= htmlspecialchars($department['place_name']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <a href="/logout" class="btn danger sm" style="margin-left:auto;">
                <?= icon('lock', '', 13) ?> Se deconnecter
            </a>
        </div>
    </div>

    <?php if ($pendingResearchers !== []): ?>
    <div class="admin-section">
        <div class="admin-pending">
            <h2><?= icon('alert-triangle', '', 16) ?> Demandes chercheurs en attente (<?= count($pendingResearchers) ?>)</h2>
            <?php foreach ($pendingResearchers as $p): ?>
            <details class="admin-pending-row">
                <summary>
                    <span class="who">
                        <span class="chevron"><?= icon('chevron-right', '', 16) ?></span>
                        <span><?= htmlspecialchars(trim($p['first_name'] . ' ' . $p['last_name'])) ?> &middot; <small><?= htmlspecialchars($p['email']) ?> &middot; labo <?= htmlspecialchars($p['lab_code']) ?></small></span>
                    </span>
                    <span class="reveal">
                        <span class="reveal-open">Voir la demande <?= icon('eye', '', 13) ?></span>
                        <span class="reveal-close">Masquer <?= icon('eye-off', '', 13) ?></span>
                    </span>
                </summary>
                <div class="admin-pending-detail">
                    <dl>
                        <dt>Laboratoire</dt>
                        <dd><?= htmlspecialchars($p['lab_name']) ?> (<?= htmlspecialchars($p['lab_code']) ?>)</dd>
                        <dt>Email</dt>
                        <dd><?= htmlspecialchars($p['email']) ?></dd>
                        <dt>Message</dt>
                        <dd>
                            <?php if (trim((string) ($p['request'] ?? '')) !== ''): ?>
                                <span class="request-text"><?= htmlspecialchars((string) $p['request']) ?></span>
                            <?php else: ?>
                                <span class="no-message">Aucun message joint a la demande.</span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                    <div class="admin-actions">
                        <form method="POST" action="/admin/researchers/approve">
                            <?= csrf_field() ?>
                            <input type="hidden" name="researcher_id" value="<?= (int) $p['researcher_id'] ?>">
                            <button class="btn success sm" type="submit"><?= icon('check', '', 13) ?> Valider</button>
                        </form>
                        <form method="POST" action="/admin/researchers/reject">
                            <?= csrf_field() ?>
                            <input type="hidden" name="researcher_id" value="<?= (int) $p['researcher_id'] ?>">
                            <button class="btn danger sm" type="submit"><?= icon('x', '', 13) ?> Refuser</button>
                        </form>
                    </div>
                </div>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($pendingResearchers === []): ?>
    <div class="admin-section">
        <div class="dashboard-card">
            <p style="color:var(--gray-400);font-size:13px;margin:0;">
                Aucune demande chercheur en attente. Les fonctionnalites de gestion
                (habilitation des enseignants, acces aux modeles) seront ajoutees ici.
            </p>
        </div>
    </div>
    <?php endif; ?>

</div>
