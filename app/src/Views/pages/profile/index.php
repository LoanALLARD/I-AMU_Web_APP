<?php
/**
 * Profile page
 *
 * @var array{id:int, email:string, first_name:string, last_name:string, roles:list<string>}|null $user
 */
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$initials = strtoupper(
    substr($user['first_name'] ?? '·', 0, 1)
    . substr($user['last_name']  ?? '·', 0, 1)
);
$roles = $user['roles'] ?? [];
$isTeacher = in_array('teacher', $roles, true);
$isSpecialized = !empty($user['isSpecialized']);

// French UI labels for roles
$roleLabels = [
    'student'          => 'étudiant',
    'teacher'          => 'enseignant',
    'department_admin' => 'admin de département',
    'admin'            => 'administrateur',
];
$roleFr = static fn(string $r): string => $roleLabels[$r] ?? $r;

// Current theme choice for the selector
$themeCur = match ($user['theme'] ?? null) {
    'LIGHT' => 'light',
    'DARK'  => 'dark',
    default => 'auto',
};
?>
<div class="page-header">
    <div class="page-header-row">
        <h1>Mon profil</h1>
        <span class="mono">compte personnel</span>
    </div>
    <p class="page-sub">Informations de votre compte et gestion de vos données personnelles.</p>
</div>

<div class="page-body">
    <div class="profile-grid">

        <div>
            <div class="dashboard-card">
                <h2>Identité</h2>
                <div class="kv-grid">
                    <span class="kv-key">prénom</span>
                    <span class="kv-val"><?= htmlspecialchars($user['first_name'] ?? '—') ?></span>
                    <span class="kv-key">nom</span>
                    <span class="kv-val"><?= htmlspecialchars($user['last_name'] ?? '—') ?></span>
                    <span class="kv-key">email</span>
                    <span class="kv-val mono"><?= htmlspecialchars($user['email'] ?? '—') ?></span>
                    <?php if ($isTeacher): ?>
                        <span class="kv-key">habilitation</span>
                        <span class="kv-val">
                            <?php if ($isSpecialized): ?>
                                <span class="badge badge-habilitated">habilité</span>
                            <?php else: ?>
                                <span class="badge badge-not-habilitated">non habilité</span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-card">
                <h2>Compte et données</h2>

                <!-- Deactivation -->
                <h3>Désactiver mon compte</h3>
                <p class="section-desc">
                    La désactivation rend votre compte inaccessible. Vous ne pourrez plus
                    vous connecter tant qu'un administrateur n'aura pas réactivé votre compte.
                </p>
                <button type="button" class="btn danger" id="btn-deactivate-account">
                    <?= icon('user-x', '', 12) ?> Désactiver mon compte
                </button>

                <hr>

                <!-- Data deletion -->
                <h3>Suppression de vos données</h3>
                <p class="section-desc">
                    Pour exercer votre droit à l'effacement (article 17 du RGPD),
                    envoyez votre demande par email au délégué à la protection des données.
                </p>
                <div class="dpo-block">
                    <a href="mailto:dpo@univ-amu.fr" class="dpo-link">
                        dpo@univ-amu.fr
                    </a>
                    <p class="dpo-hint">
                        Précisez votre nom, prénom et adresse email universitaire.
                        Délai de traitement : 30 jours.
                    </p>
                </div>
                <a href="/rgpd_consent" class="rgpd-link">Consulter les mentions d'information RGPD</a>
            </div>
        </div>

        <aside>
            <div class="profile-aside-card">
                <div class="profile-avatar">
                    <?= htmlspecialchars($initials) ?>
                </div>
                <div class="profile-display-name"><?= htmlspecialchars($displayName) ?></div>
                <?php if ($roles !== []): ?>
                    <div class="profile-roles">
                        <?php foreach ($roles as $role): ?>
                            <span class="badge badge-<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($roleFr($role)) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="profile-no-role">aucun rôle</div>
                <?php endif; ?>
                <a href="/logout" class="profile-logout">
                    <?= icon('lock', '', 12) ?> Se déconnecter
                </a>
            </div>

            <div class="profile-card">
                <h2>Apparence</h2>
                <p class="page-sub">
                    Thème de l'interface. « Automatique » suit le réglage de votre appareil.
                </p>
                <hr>
                <form method="POST" action="/profile/theme" class="theme-select">
                    <?= csrf_field() ?>
                    <button type="submit" name="theme" value="auto" class="theme-opt<?= $themeCur === 'auto' ? ' is-active' : '' ?>">Automatique</button>
                    <button type="submit" name="theme" value="light" class="theme-opt<?= $themeCur === 'light' ? ' is-active' : '' ?>">Clair</button>
                    <button type="submit" name="theme" value="dark" class="theme-opt<?= $themeCur === 'dark' ? ' is-active' : '' ?>">Sombre</button>
                </form>
            </div>
        </aside>
    </div>
</div>

<!-- Deactivation confirmation modal -->
<div class="modal-overlay" id="modal-deactivate">
    <div class="modal-box">
        <h2>Confirmer la désactivation</h2>
        <p>Êtes-vous sûr de vouloir désactiver votre compte ?</p>
        <ul>
            <li>Vous serez immédiatement déconnecté</li>
            <li>Vous ne pourrez plus vous connecter</li>
            <li>Vos données seront conservées à des fins de recherche</li>
            <li>Pour supprimer vos données, contactez <strong>dpo@univ-amu.fr</strong></li>
        </ul>

        <form method="POST" action="/profile/deactivate" class="modal-actions">
            <?= csrf_field() ?>
            <button type="button" class="btn btn-cancel" id="btn-cancel-deactivate">
                Annuler
            </button>
            <button type="submit" class="btn danger">
                Désactiver mon compte
            </button>
        </form>
    </div>
</div>

<script>
    (function() {
        const btnOpen   = document.getElementById('btn-deactivate-account');
        const btnCancel = document.getElementById('btn-cancel-deactivate');
        const modal     = document.getElementById('modal-deactivate');

        if (!btnOpen || !modal) return;

        btnOpen.addEventListener('click', function() {
            modal.style.display = 'flex';
        });

        btnCancel.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        // Close on backdrop click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                modal.style.display = 'none';
            }
        });
    })();
</script>