<?php
/**
 * Researcher analysis space: browse and analyse the conversations of the
 * departments the researcher has access to. Not implemented yet.
 *
 * @var array{id:int, email:string, first_name:string, last_name:string, roles:list<string>, department_id:int|null}|null $user
 */
$activeTab = 'analysis';
require __DIR__ . '/_header.php';
?>
<div class="page-body">

    <div class="admin-section">
        <h2><?= icon('chart-line', '', 16) ?> Analyse</h2>
        <p class="no-message">La consultation et l'analyse des données seront disponibles prochainement.</p>
    </div>

</div>
