<?php
/**
 * Sites (places) and departments management.
 *
 * @var list<array{id:int, name:string, address:?string, city:?string, zip_code:?string}> $places
 * @var list<array{id:int, place_id:int, place_name:string, name:string, description:?string, is_active:bool}> $departments
 */
$places      = $places      ?? [];
$departments = $departments ?? [];
?>
<div class="superadmin-dashboard">

    <?php require __DIR__ . '/_header.php'; ?>

    <main class="dashboard-content">

        <?php require __DIR__ . '/_flash.php'; ?>

        <section class="panel-section">
            <h2>Ajouter un site</h2>
            <p class="section-lead">
                Un site est un lieu physique (campus, batiment) auquel sont rattaches
                un ou plusieurs departements.
            </p>

            <form method="POST" action="/super-admin/places" class="place-form">
                <?= csrf_field() ?>
                <div class="domain-form-row">
                    <div class="form-group">
                        <label for="place-name">Nom</label>
                        <input type="text" id="place-name" name="name"
                               placeholder="Campus Saint-Charles" maxlength="255" required>
                    </div>
                    <div class="form-group">
                        <label for="place-address">Adresse</label>
                        <input type="text" id="place-address" name="address"
                               placeholder="3 place Victor Hugo" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="place-city">Ville</label>
                        <input type="text" id="place-city" name="city"
                               placeholder="Marseille" maxlength="50">
                    </div>
                    <div class="form-group form-group--narrow">
                        <label for="place-zip">Code postal</label>
                        <input type="text" id="place-zip" name="zip_code"
                               placeholder="13003" maxlength="10">
                    </div>
                    <button type="submit" class="btn-submit">
                        <?= icon('plus', '', 16) ?>
                        Ajouter
                    </button>
                </div>
            </form>
        </section>

        <section class="panel-section">
            <h2>Sites configures</h2>

            <?php if (empty($places)): ?>
                <p class="section-empty">Aucun site configure pour le moment.</p>
            <?php else: ?>
                <table class="domain-table sortable">
                    <thead>
                        <tr>
                            <th data-sort="text">Nom</th>
                            <th data-sort="text">Adresse</th>
                            <th class="col-city" data-sort="text">Ville</th>
                            <th class="col-zip" data-sort="text">Code postal</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($places as $p): ?>
                            <tr>
                                <td class="cell-domain"><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= htmlspecialchars($p['address'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['city'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['zip_code'] ?? '') ?></td>
                                <td class="col-action">
                                    <form method="POST" action="/super-admin/places/delete"
                                          onsubmit="return confirm('Supprimer ce site ?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit" class="btn-row btn-row-danger">
                                            <?= icon('x', '', 15) ?> Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="panel-section">
            <h2>Ajouter un departement</h2>
            <p class="section-lead">
                Un departement appartient a un site. Les utilisateurs le rejoignent
                a l'inscription via un code de departement.
            </p>

            <?php if (empty($places)): ?>
                <p class="section-empty">
                    Creez d'abord un site pour pouvoir y rattacher un departement.
                </p>
            <?php else: ?>
                <form method="POST" action="/super-admin/departments" class="department-form">
                    <?= csrf_field() ?>
                    <div class="domain-form-row">
                        <div class="form-group">
                            <label for="dept-place">Site</label>
                            <select id="dept-place" name="place_id" required>
                                <?php foreach ($places as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="dept-name">Nom</label>
                            <input type="text" id="dept-name" name="name"
                                   placeholder="Informatique" maxlength="50" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="dept-description">Description</label>
                        <textarea id="dept-description" name="description" rows="2"
                                  placeholder="Description du departement (optionnel)"></textarea>
                    </div>
                    <button type="submit" class="btn-submit">
                        <?= icon('plus', '', 16) ?>
                        Ajouter
                    </button>
                </form>
            <?php endif; ?>
        </section>

        <section class="panel-section">
            <h2>Departements configures</h2>

            <?php if (empty($departments)): ?>
                <p class="section-empty">Aucun departement configure pour le moment.</p>
            <?php else: ?>
                <table class="domain-table sortable">
                    <thead>
                        <tr>
                            <th data-sort="text">Nom</th>
                            <th data-sort="text">Site</th>
                            <th class="col-state" data-sort="text">Etat</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $d): ?>
                            <tr class="<?= $d['is_active'] ? '' : 'is-inactive' ?>">
                                <td class="cell-domain">
                                    <?= htmlspecialchars($d['name']) ?>
                                    <?php if (!empty($d['description'])): ?>
                                        <span class="cell-subtitle"><?= htmlspecialchars($d['description']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($d['place_name']) ?></td>
                                <td data-sort-value="<?= $d['is_active'] ? '1' : '0' ?>">
                                    <?php if ($d['is_active']): ?>
                                        <span class="badge-state badge-active">Actif</span>
                                    <?php else: ?>
                                        <span class="badge-state badge-inactive">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-action">
                                    <form method="POST" action="/super-admin/departments/toggle">
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

<?php
$sortJs  = __DIR__ . '/../../../../public/assets/js/table-sort.js';
$sortVer = is_file($sortJs) ? filemtime($sortJs) : 0;
?>
<script src="/assets/js/table-sort.js?v=<?= $sortVer ?>" defer></script>
