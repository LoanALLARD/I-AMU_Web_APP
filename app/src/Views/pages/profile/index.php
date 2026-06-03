<?php
/**
 * Profile page — minimal MVP, lives under the same authenticated layout
 * as the sessions/chat pages. To be enriched by spec 01 (password change,
 * account settings, RGPD opt-outs, …).
 *
 * @var array{id:int, email:string, first_name:string, last_name:string, roles:list<string>}|null $user
 */
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$initials = strtoupper(
    substr($user['first_name'] ?? '·', 0, 1)
    . substr($user['last_name']  ?? '·', 0, 1)
);
$roles = $user['roles'] ?? [];
?>
<div class="page-header">
    <div class="page-header-row">
        <h1>Mon profil</h1>
        <span class="mono" style="font-size:11px;color:var(--gray-400);">compte personnel</span>
    </div>
    <p class="page-sub">Informations de votre compte et gestion de vos données personnelles.</p>
</div>

<div class="page-body">
    <div class="dashboard-grid" style="max-width: 880px; margin: 0 auto; padding: 0;">

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
                    <span class="kv-key">id interne</span>
                    <span class="kv-val mono">#<?= (int) ($user['id'] ?? 0) ?></span>
                </div>
            </div>

            <div class="dashboard-card">
                <h2>Rôles</h2>
                <?php if ($roles === []): ?>
                    <p style="color:var(--gray-400);font-size:12px;">Aucun rôle attribué.</p>
                <?php else: ?>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <?php foreach ($roles as $role): ?>
                            <span class="badge badge-<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dashboard-card">
                <h2>Sécurité</h2>
                <a href="/logout" class="btn danger">
                    <?= icon('lock', '', 12) ?> Se déconnecter
                </a>
            </div>

            <div class="dashboard-card">
                <div style="background:var(--gray-50, #f9fafb);border:1px solid var(--gray-200, #e5e7eb);border-radius:8px;padding:16px;margin-bottom:16px;">
                    <h3 style="font-size:14px;font-weight:600;margin:0 0 8px;color:var(--gray-700, #374151);">
                        Désactiver mon compte
                    </h3>
                    <p style="font-size:12px;color:var(--gray-500);line-height:1.5;margin:0 0 12px;">
                        La désactivation rend votre compte inaccessible. Vous ne pourrez plus
                        vous connecter tant qu'un administrateur n'aura pas réactivé votre compte.
                    </p>
                    <button type="button" class="btn danger" id="btn-deactivate-account">
                        <?= icon('user-x', '', 12) ?> Désactiver mon compte
                    </button>
                </div>

                <div style="background:var(--gray-50, #f9fafb);border:1px solid var(--gray-200, #e5e7eb);border-radius:8px;padding:16px;">
                    <h3 style="font-size:14px;font-weight:600;margin:0 0 8px;color:var(--gray-700, #374151);">
                        Suppression de vos données
                    </h3>
                    <p style="font-size:12px;color:var(--gray-500);line-height:1.5;margin:0 0 8px;">
                        Pour exercer votre droit à l'effacement et demander la suppression
                        définitive de vos données personnelles, veuillez envoyer votre demande
                        par email à l'adresse suivante :
                    </p>
                    <p style="margin:0;">
                        <a href="mailto:dpo@univ-amu.fr" class="mono" style="font-size:13px;font-weight:600;color:var(--primary, #2563eb);">
                            dpo@univ-amu.fr
                        </a>
                    </p>
                    <p style="font-size:11px;color:var(--gray-400);line-height:1.4;margin:8px 0 0;">
                        Précisez votre nom, prénom et adresse email universitaire dans votre demande.
                        Le responsable de traitement traitera votre requête dans un délai de 30 jours
                        conformément à l'article 17 du RGPD.
                    </p>
                </div>
            </div>
        </div>

        <aside>
            <div class="dashboard-card" style="text-align:center;">
                <div class="user-pill-avatar" style="width:72px;height:72px;font-size:24px;margin: 12px auto 16px;">
                    <?= htmlspecialchars($initials) ?>
                </div>
                <div style="font-weight:600;font-size:16px;"><?= htmlspecialchars($displayName) ?></div>
                <div class="mono" style="font-size:11px;color:var(--gray-400);margin-top:4px;">
                    <?= htmlspecialchars(implode(' · ', $roles) ?: 'aucun rôle') ?>
                </div>
            </div>
        </aside>

    </div>
</div>
<div id="modal-deactivate" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--white, #fff);border-radius:12px;padding:28px 32px;max-width:460px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <h2 style="margin:0 0 12px;font-size:18px;color:var(--gray-800, #1f2937);">
            Confirmer la désactivation
        </h2>
        <p style="font-size:13px;color:var(--gray-600, #4b5563);line-height:1.6;margin:0 0 8px;">
            Êtes-vous sûr de vouloir désactiver votre compte ?
        </p>
        <ul style="font-size:12px;color:var(--gray-500);line-height:1.6;margin:0 0 20px;padding-left:18px;">
            <li>Vous serez immédiatement déconnecté</li>
            <li>Vous ne pourrez plus vous connecter</li>
            <li>Vos données seront conservées à des fins de recherche</li>
            <li>Pour supprimer vos données, contactez <strong>dpo@univ-amu.fr</strong></li>
        </ul>

        <form method="POST" action="/profile/deactivate" style="display:flex;gap:10px;justify-content:flex-end;">
            <?= csrf_field() ?>
            <button type="button" class="btn" id="btn-cancel-deactivate" style="background:var(--gray-100, #f3f4f6);color:var(--gray-700, #374151);">
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

