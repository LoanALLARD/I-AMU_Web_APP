<?php
/**
 * Sites (places) and departments management.
 * Master-detail: a list of places on the left; clicking one reveals its
 * departments on the right (client-side, all data is already in the page).
 *
 * @var list<array{id:int, name:string, address:?string, city:?string, zip_code:?string, display_name:?string, logo_path:?string, primary_color:?string}> $places
 * @var list<array{id:int, place_id:int, place_name:string, name:string, description:?string, is_active:bool}> $departments
 */
$places      = $places      ?? [];
$departments = $departments ?? [];

// Branding defaults: a place leaves these NULL to inherit the I-AMU identity.
$defaultDisplayName = 'I-AMU';
$defaultColor       = '#1a73c8';

// Group departments by their place so each detail panel renders its own.
$departmentsByPlace = [];
foreach ($departments as $d) {
    $departmentsByPlace[$d['place_id']][] = $d;
}
?>
<div class="superadmin-dashboard">

    <?php require __DIR__ . '/_header.php'; ?>

    <main class="dashboard-content">

        <?php require __DIR__ . '/_flash.php'; ?>

        <div class="places-layout">

            <!-- Left column: the list of places + the add-place form -->
            <section class="panel-section places-master">
                <h2>Sites</h2>
                <p class="section-lead">Selectionnez un site pour voir ses departements.</p>

                <?php if (empty($places)): ?>
                    <p class="section-empty">Aucun site configure pour le moment.</p>
                <?php else: ?>
                    <ul class="place-list">
                        <?php foreach ($places as $p): ?>
                            <li>
                                <button type="button" class="place-item" data-place-id="<?= (int) $p['id'] ?>">
                                    <span class="place-item-main">
                                        <span class="place-item-name"><?= htmlspecialchars($p['name']) ?></span>
                                        <?php
                                        $location = trim(implode(', ', array_filter([
                                            $p['city'] ?? '',
                                            $p['zip_code'] ?? '',
                                        ])));
                                        ?>
                                        <?php if ($location !== ''): ?>
                                            <span class="place-item-sub"><?= htmlspecialchars($location) ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="place-item-count">
                                        <?= count($departmentsByPlace[$p['id']] ?? []) ?>
                                        <?= icon('chevron-right', 'place-item-chevron', 16) ?>
                                    </span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="POST" action="/super-admin/places" class="place-form place-add-form">
                    <?= csrf_field() ?>
                    <h3>Ajouter un site</h3>
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
                    <div class="place-add-row">
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
                    </div>
                    <button type="submit" class="btn-submit">
                        <?= icon('plus', '', 16) ?>
                        Ajouter le site
                    </button>
                </form>
            </section>

            <!-- Right column: one detail panel per place, shown on selection -->
            <section class="panel-section places-detail">

                <div class="places-detail-empty" data-place-empty>
                    <span class="page-placeholder-icon"><?= icon('building', '', 28) ?></span>
                    <p>Selectionnez un site dans la liste pour gerer ses departements.</p>
                </div>

                <?php foreach ($places as $p): ?>
                    <div class="place-detail-panel" data-place-panel="<?= (int) $p['id'] ?>" hidden>
                        <h2><?= htmlspecialchars($p['name']) ?></h2>
                        <?php
                        $address = trim(implode(', ', array_filter([
                            $p['address'] ?? '',
                            $p['zip_code'] ?? '',
                            $p['city'] ?? '',
                        ])));
                        ?>
                        <p class="section-lead">
                            <?= $address !== '' ? htmlspecialchars($address) : 'Adresse non renseignee.' ?>
                        </p>

                        <?php
                        $brandColor = $p['primary_color'] ?? $defaultColor;
                        $brandName  = $p['display_name'] ?? '';
                        ?>
                        <form method="POST" action="/super-admin/places/branding" class="place-branding"
                              enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <h3>Personnalisation du lieu</h3>
                            <p class="branding-hint">
                                Laissez vide pour utiliser l'identite I-AMU par defaut.
                            </p>

                            <div class="branding-row">
                                <div class="form-group branding-logo">
                                    <label for="branding-logo-<?= (int) $p['id'] ?>">Logo</label>
                                    <div class="branding-logo-field">
                                        <span class="branding-logo-preview">
                                            <?php if (!empty($p['logo_path'])): ?>
                                                <img src="<?= htmlspecialchars($p['logo_path']) ?>" alt="Logo du lieu">
                                            <?php else: ?>
                                                <?= icon('building', '', 26) ?>
                                            <?php endif; ?>
                                        </span>
                                        <input type="file" id="branding-logo-<?= (int) $p['id'] ?>"
                                               name="logo" accept="image/*">
                                    </div>
                                    <?php if (!empty($p['logo_path'])): ?>
                                        <label style="display:inline-flex;align-items:center;gap:.35rem;font-size:12px;cursor:pointer;color:var(--refuse);margin-top:.45rem;">
                                            <input type="checkbox" name="remove_logo" value="1"> Supprimer (revenir au logo I-AMU)
                                        </label>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group branding-favicon">
                                    <label for="branding-favicon-<?= (int) $p['id'] ?>">Favicon</label>
                                    <div class="branding-logo-field">
                                        <span class="branding-logo-preview">
                                            <?php if (!empty($p['favicon_path'])): ?>
                                                <img src="<?= htmlspecialchars($p['favicon_path']) ?>" alt="Favicon du lieu">
                                            <?php else: ?>
                                                <?= icon('image', '', 26) ?>
                                            <?php endif; ?>
                                        </span>
                                        <input type="file" id="branding-favicon-<?= (int) $p['id'] ?>"
                                               name="favicon" accept="image/*">
                                    </div>
                                    <?php if (!empty($p['favicon_path'])): ?>
                                        <label style="display:inline-flex;align-items:center;gap:.35rem;font-size:12px;cursor:pointer;color:var(--refuse);margin-top:.45rem;">
                                            <input type="checkbox" name="remove_favicon" value="1"> Supprimer (favicon par défaut)
                                        </label>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group branding-name">
                                    <label for="branding-name-<?= (int) $p['id'] ?>">Nom de l'application</label>
                                    <input type="text" id="branding-name-<?= (int) $p['id'] ?>"
                                           name="display_name" maxlength="255"
                                           value="<?= htmlspecialchars($brandName) ?>"
                                           placeholder="<?= htmlspecialchars($defaultDisplayName) ?>">
                                </div>

                                <div class="form-group branding-color">
                                    <label for="branding-color-<?= (int) $p['id'] ?>">Couleur</label>
                                    <input type="color" id="branding-color-<?= (int) $p['id'] ?>"
                                           name="primary_color" value="<?= htmlspecialchars($brandColor) ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn-row btn-branding-save">
                                <?= icon('check', '', 15) ?> Enregistrer la personnalisation
                            </button>
                        </form>

                        <?php $deps = $departmentsByPlace[$p['id']] ?? []; ?>
                        <?php if (empty($deps)): ?>
                            <p class="section-empty">Aucun departement sur ce site.</p>
                        <?php else: ?>
                            <table class="domain-table">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th class="col-state">Etat</th>
                                        <th class="col-action">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deps as $d): ?>
                                        <tr class="<?= $d['is_active'] ? '' : 'is-inactive' ?>">
                                            <td class="cell-domain">
                                                <?= htmlspecialchars($d['name']) ?>
                                                <?php if (!empty($d['description'])): ?>
                                                    <span class="cell-subtitle"><?= htmlspecialchars($d['description']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
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

                        <form method="POST" action="/super-admin/departments" class="department-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="place_id" value="<?= (int) $p['id'] ?>">
                            <h3>Ajouter un departement</h3>
                            <div class="form-group">
                                <label for="dept-name-<?= (int) $p['id'] ?>">Nom</label>
                                <input type="text" id="dept-name-<?= (int) $p['id'] ?>" name="name"
                                       placeholder="Informatique" maxlength="50" required>
                            </div>
                            <div class="form-group">
                                <label for="dept-description-<?= (int) $p['id'] ?>">Description</label>
                                <textarea id="dept-description-<?= (int) $p['id'] ?>" name="description" rows="2"
                                          placeholder="Description du departement (optionnel)"></textarea>
                            </div>
                            <button type="submit" class="btn-submit">
                                <?= icon('plus', '', 16) ?>
                                Ajouter le departement
                            </button>
                        </form>

                        <?php if (empty($deps)): ?>
                            <form method="POST" action="/super-admin/places/delete" class="place-delete-form"
                                  onsubmit="return confirm('Supprimer ce site ?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="btn-row btn-row-danger">
                                    <?= icon('x', '', 15) ?> Supprimer le site
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>
    </main>
</div>

<?php
$placesJs  = __DIR__ . '/../../../../public/assets/js/places.js';
$placesVer = is_file($placesJs) ? filemtime($placesJs) : 0;
?>
<script src="/assets/js/places.js?v=<?= $placesVer ?>" defer></script>
