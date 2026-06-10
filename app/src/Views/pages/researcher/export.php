<?php
/**
 * Researcher export space: pick the campuses/departments to include, then
 * download the research corpus (every interaction of consenting users) as
 * JSON or CSV. Scope and consent are enforced server-side.
 *
 * @var array{id:int, email:string, first_name:string, last_name:string, roles:list<string>, department_id:int|null}|null $user
 * @var list<array{place_id:int, place_name:string, departments:list<array{department_id:int, department_name:string}>}> $places
 */
$places    = $places ?? [];
$activeTab = 'export';
require __DIR__ . '/_header.php';
?>
<style>
    .export-scope { max-width:680px; }
    .export-campus { border:1px solid var(--gray-200); border-radius:var(--radius); padding:10px 14px; margin:0 0 12px; }
    .export-campus > legend { padding:0 6px; font-weight:600; }
    .export-depts { display:flex; flex-wrap:wrap; gap:8px 18px; padding:6px 4px 2px 4px; }
    .export-check { display:inline-flex; align-items:center; gap:7px; font-size:14px; cursor:pointer; }
    .export-check input { cursor:pointer; }
    .export-actions { display:flex; gap:10px; margin-top:16px; }
    .export-empty { color:var(--refuse); margin-top:10px; }
</style>

<div class="page-body">

    <div class="admin-section">
        <h2><?= icon('download', '', 16) ?> Export</h2>

        <?php if ($places === []): ?>
            <p class="no-message">Vous n'avez accès aux données d'aucun département pour le moment. Demandez un accès depuis l'onglet « Mes accès ».</p>
        <?php else: ?>
            <p class="section-lead">
                Sélectionnez les campus et départements à inclure, puis exportez le corpus.
                Seules les interactions des utilisateurs n'ayant pas refusé l'usage recherche sont incluses.
            </p>

            <form id="export-form" class="export-scope">
                <?php foreach ($places as $place): ?>
                    <fieldset class="export-campus" data-campus>
                        <legend>
                            <label class="export-check">
                                <input type="checkbox" class="campus-toggle" checked>
                                <?= icon('building', '', 15) ?> <?= htmlspecialchars($place['place_name']) ?>
                            </label>
                        </legend>
                        <div class="export-depts">
                            <?php foreach ($place['departments'] as $dept): ?>
                                <label class="export-check">
                                    <input type="checkbox" name="departments" value="<?= (int) $dept['department_id'] ?>" checked>
                                    <?= htmlspecialchars($dept['department_name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>

                <div class="export-actions">
                    <button type="button" class="btn btn-primary" data-format="json">
                        <?= icon('database', '', 14) ?> Exporter en JSON
                    </button>
                    <button type="button" class="btn" data-format="csv">
                        <?= icon('download', '', 14) ?> Exporter en CSV
                    </button>
                </div>
                <p class="no-message export-empty" hidden>Sélectionnez au moins un département.</p>
            </form>
        <?php endif; ?>
    </div>

</div>

<script>
    (function () {
        var form = document.getElementById('export-form');
        if (!form) { return; }
        var empty = form.querySelector('.export-empty');

        // A campus checkbox mirrors its departments, and stays in sync when a
        // department is toggled individually.
        form.querySelectorAll('[data-campus]').forEach(function (fs) {
            var campus = fs.querySelector('.campus-toggle');
            var depts = fs.querySelectorAll('input[name="departments"]');
            campus.addEventListener('change', function () {
                depts.forEach(function (d) { d.checked = campus.checked; });
            });
            depts.forEach(function (d) {
                d.addEventListener('change', function () {
                    campus.checked = Array.prototype.some.call(depts, function (x) { return x.checked; });
                });
            });
        });

        form.querySelectorAll('[data-format]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var ids = Array.prototype.map.call(
                    form.querySelectorAll('input[name="departments"]:checked'),
                    function (c) { return c.value; }
                );
                if (ids.length === 0) {
                    if (empty) { empty.hidden = false; }
                    return;
                }
                if (empty) { empty.hidden = true; }
                window.location.href = '/researcher/export/download?format=' + btn.dataset.format
                    + '&departments=' + ids.join(',');
            });
        });
    })();
</script>
