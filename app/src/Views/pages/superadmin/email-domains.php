<?php
/**
 * @var list<array{id:int, domain:string, role:string, is_active:bool}> $domains
 * @var list<string> $roles
 */
// French labels for known roles; unmapped enum values fall back to their raw name.
$roleLabels = [
    'STUDENT'    => 'Étudiant',
    'TEACHER'    => 'Enseignant',
    'RESEARCHER' => 'Chercheur',
];
$roleLabel = static fn (string $role): string => $roleLabels[$role] ?? $role;
?>
<div class="superadmin-dashboard">

    <?php require __DIR__ . '/_header.php'; ?>

    <main class="dashboard-content">

        <?php require __DIR__ . '/_flash.php'; ?>

        <section class="panel-section">
            <h2>Ajouter un domaine</h2>
            <p class="section-lead">
                Les utilisateurs dont l'adresse e-mail se termine par un domaine actif
                peuvent s'inscrire avec le rôle associé.
            </p>

            <form method="POST" action="/super-admin/email-domains" class="domain-form">
                <?= csrf_field() ?>
                <div class="domain-form-row">
                    <div class="form-group">
                        <label for="domain">Domaine</label>
                        <input type="text" id="domain" name="domain"
                               placeholder="univ-amu.fr" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Rôle</label>
                        <select id="role" name="role" required>
                            <?php foreach ($roles as $value): ?>
                                <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($roleLabel($value)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit">
                        <?= icon('plus', '', 16) ?>
                        Ajouter
                    </button>
                </div>
            </form>
        </section>

        <section class="panel-section">
            <h2>Domaines configurés</h2>

            <?php if (empty($domains)): ?>
                <p class="section-empty">Aucun domaine configuré pour le moment.</p>
            <?php else: ?>
                <table class="domain-table sortable">
                    <thead>
                        <tr>
                            <th data-sort="text">Domaine</th>
                            <th class="col-role" data-sort="text">Rôle</th>
                            <th class="col-state" data-sort="text">État</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($domains as $d): ?>
                            <tr class="<?= $d['is_active'] ? '' : 'is-inactive' ?>">
                                <td class="cell-domain" data-label="Domaine"><?= htmlspecialchars($d['domain']) ?></td>
                                <td class="cell-role" data-label="Rôle" data-sort-value="<?= htmlspecialchars($roleLabel($d['role'])) ?>">
                                    <span class="role-view">
                                        <span class="role-label"><?= htmlspecialchars($roleLabel($d['role'])) ?></span>
                                        <button type="button" class="btn-icon role-edit-toggle" title="Modifier le rôle" aria-label="Modifier le rôle">
                                            <?= icon('edit', '', 15) ?>
                                        </button>
                                    </span>
                                    <form method="POST" action="/super-admin/email-domains/role" class="role-form" hidden>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                                        <select name="role" class="role-select" data-initial="<?= htmlspecialchars($d['role']) ?>" aria-label="Rôle du domaine">
                                            <?php foreach ($roles as $value): ?>
                                                <option value="<?= htmlspecialchars($value) ?>" <?= $value === $d['role'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($roleLabel($value)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn-icon role-save" title="Enregistrer le rôle" aria-label="Enregistrer le rôle" disabled>
                                            <?= icon('check', '', 15) ?>
                                        </button>
                                        <button type="button" class="btn-icon role-cancel" title="Annuler" aria-label="Annuler">
                                            <?= icon('x', '', 15) ?>
                                        </button>
                                    </form>
                                </td>
                                <td data-label="État" data-sort-value="<?= $d['is_active'] ? '1' : '0' ?>">
                                    <?php if ($d['is_active']): ?>
                                        <span class="badge-state badge-active">Actif</span>
                                    <?php else: ?>
                                        <span class="badge-state badge-inactive">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-action" data-label="Action">
                                    <form method="POST" action="/super-admin/email-domains/toggle">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $d['is_active'] ? '0' : '1' ?>">
                                        <?php if ($d['is_active']): ?>
                                            <button type="submit" class="btn-row btn-row-danger">
                                                <?= icon('x', '', 15) ?> Désactiver
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn-row">
                                                <?= icon('check', '', 15) ?> Réactiver
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php
$sortJs    = __DIR__ . '/../../../../public/assets/js/table-sort.js';
$domainsJs = __DIR__ . '/../../../../public/assets/js/email-domains.js';
$sortVer    = is_file($sortJs) ? filemtime($sortJs) : 0;
$domainsVer = is_file($domainsJs) ? filemtime($domainsJs) : 0;
?>
<script src="/assets/js/table-sort.js?v=<?= $sortVer ?>" defer></script>
<script src="/assets/js/email-domains.js?v=<?= $domainsVer ?>" defer></script>
