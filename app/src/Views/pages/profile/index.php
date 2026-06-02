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
        <span class="mono" style="font-size:11px;color:var(--ink-3);">compte personnel</span>
    </div>
    <p class="page-sub">Informations de votre compte. Les options de modification arriveront avec la spec d'authentification.</p>
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
                    <p style="color:var(--ink-3);font-size:12px;">Aucun rôle attribué.</p>
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
                <p style="color:var(--ink-3);font-size:12px;margin: 0 0 14px;">
                    Changement de mot de passe et options RGPD à venir (specs 01 + 06).
                </p>
                <a href="/logout" class="btn danger">
                    <?= icon('lock', '', 12) ?> Se déconnecter
                </a>
            </div>
        </div>

        <aside>
            <div class="dashboard-card" style="text-align:center;">
                <div class="user-pill-avatar" style="width:72px;height:72px;font-size:24px;margin: 12px auto 16px;">
                    <?= htmlspecialchars($initials) ?>
                </div>
                <div style="font-weight:600;font-size:16px;"><?= htmlspecialchars($displayName) ?></div>
                <div class="mono" style="font-size:11px;color:var(--ink-3);margin-top:4px;">
                    <?= htmlspecialchars(implode(' · ', $roles) ?: 'aucun rôle') ?>
                </div>
            </div>
        </aside>

    </div>
</div>
