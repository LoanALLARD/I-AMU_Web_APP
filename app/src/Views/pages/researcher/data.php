<?php
/**
 * Researcher data space: browse and export the conversations of the
 * departments the researcher has access to. Export and preview are not
 * implemented yet.
 *
 * @var array{id:int, email:string, first_name:string, last_name:string, roles:list<string>, department_id:int|null}|null $user
 */
$activeTab = 'data';
require __DIR__ . '/_header.php';
?>
<div class="page-body">

    <div class="admin-section">
        <h2><?= icon('database', '', 16) ?> Données &amp; export</h2>
        <p class="no-message">La consultation et l'export des données seront disponibles prochainement.</p>
    </div>

</div>
