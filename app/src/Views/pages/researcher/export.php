<?php
/**
 * Researcher export space: export the conversations of the departments the
 * researcher has access to. Not implemented yet.
 *
 * @var array{id:int, email:string, first_name:string, last_name:string, roles:list<string>, department_id:int|null}|null $user
 */
$activeTab = 'export';
require __DIR__ . '/_header.php';
?>
<div class="page-body">

    <div class="admin-section">
        <h2><?= icon('download', '', 16) ?> Export</h2>
        <p class="no-message">L'export des données sera disponible prochainement.</p>
    </div>

</div>
