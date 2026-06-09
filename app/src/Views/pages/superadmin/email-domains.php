<?php
/**
 * @var list<array{id:int, domain:string, role:string, is_active:bool}> $domains
 * @var list<string> $roles
 */
// French labels for known roles; unmapped enum values fall back to their raw name.
$roleLabels = [
    'STUDENT'    => 'Etudiant',
    'TEACHER'    => 'Enseignant',
    'RESEARCHER' => 'Chercheur',
];
$roleLabel = static fn (string $role): string => $roleLabels[$role] ?? $role;
?>
<div class="superadmin-dashboard">

    <?php require __DIR__ . '/_header.php'; ?>

    <main class="dashboard-content">

        <?php if (!empty($_SESSION['_flash'])): ?>
            <?php foreach ($_SESSION['_flash'] as $flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endforeach; ?>
            <?php unset($_SESSION['_flash']); ?>
        <?php endif; ?>

        <section class="panel-section">
            <h2>Ajouter un domaine</h2>
            <p class="section-lead">
                Les utilisateurs dont l'adresse email se termine par un domaine actif
                peuvent s'inscrire avec le role associe.
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
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <?php foreach ($roles as $value): ?>
                                <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($roleLabel($value)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit">
                        <?= icon('check', '', 16) ?>
                        Ajouter
                    </button>
                </div>
            </form>
        </section>

        <section class="panel-section">
            <h2>Domaines configures</h2>

            <?php if (empty($domains)): ?>
                <p class="section-empty">Aucun domaine configure pour le moment.</p>
            <?php else: ?>
                <table class="domain-table">
                    <thead>
                        <tr>
                            <th>Domaine</th>
                            <th>Role</th>
                            <th>Etat</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($domains as $d): ?>
                            <tr class="<?= $d['is_active'] ? '' : 'is-inactive' ?>">
                                <td class="cell-domain"><?= htmlspecialchars($d['domain']) ?></td>
                                <td><?= htmlspecialchars($roleLabel($d['role'])) ?></td>
                                <td>
                                    <?php if ($d['is_active']): ?>
                                        <span class="badge-state badge-active">Actif</span>
                                    <?php else: ?>
                                        <span class="badge-state badge-inactive">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-action">
                                    <form method="POST" action="/super-admin/email-domains/toggle">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $d['is_active'] ? '0' : '1' ?>">
                                        <?php if ($d['is_active']): ?>
                                            <button type="submit" class="btn-row btn-row-danger">
                                                <?= icon('x', '', 15) ?> Desactiver
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn-row">
                                                <?= icon('check', '', 15) ?> Reactiver
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
