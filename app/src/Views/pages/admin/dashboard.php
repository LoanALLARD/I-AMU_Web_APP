<?php
/**
 * Department-admin landing page — minimal MVP under the authenticated
 * layout. Management actions (teacher habilitation, researcher
 * authorizations, model access) will be added with spec 05.
 *
 * @var array{id:int, email:string, first_name:string, last_name:string, roles:list<string>}|null $user
 */
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
?>
<div class="page-header">
    <div class="page-header-row">
        <h1>Administration</h1>
        <span class="mono" style="font-size:11px;color:var(--gray-400);">admin de département</span>
    </div>
    <p class="page-sub">Espace d'administration de votre département. Les actions de gestion arriveront avec la spec 05.</p>
</div>

<div class="page-body">
    <div class="dashboard-card" style="max-width: 880px; margin: 0 auto;">
        <h2>Bienvenue, <?= htmlspecialchars($displayName !== '' ? $displayName : 'administrateur') ?></h2>
        <p style="color:var(--gray-400);font-size:12px;margin: 0 0 14px;">
            Vous êtes connecté en tant qu'administrateur de département. Les
            fonctionnalités de gestion (habilitation des enseignants,
            autorisations chercheurs, accès aux modèles) seront ajoutées ici.
        </p>
        <a href="/logout" class="btn danger">
            <?= icon('lock', '', 12) ?> Se déconnecter
        </a>
    </div>
</div>
