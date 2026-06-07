<?php
/**
 * Researcher landing page.
 *
 * @var array{id:int, email:string, first_name:string, last_name:string, roles:list<string>, department_id:int|null}|null $user
 */
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
?>
<div class="page-header">
    <div class="page-header-row">
        <h1>Espace chercheur</h1>
        <span class="badge badge-draft"><?= icon('flask-conical', '', 13) ?> chercheur</span>
    </div>
    <p class="page-sub">Connexion validee. Votre espace dedie sera enrichi prochainement.</p>
</div>

<div class="page-body">
    <div class="admin-section">
        <div class="admin-identity">
            <span class="admin-identity-icon"><?= icon('user', '', 18) ?></span>
            <div>
                <div class="admin-identity-name">
                    Connecte en tant que <strong><?= htmlspecialchars($displayName !== '' ? $displayName : 'chercheur') ?></strong>
                </div>
                <div class="admin-identity-meta"><?= htmlspecialchars($user['email'] ?? '') ?></div>
            </div>
        </div>
    </div>
</div>
