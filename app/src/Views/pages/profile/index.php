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
// Only teachers and students use the LLM, so only they get usage stats.
$usesLlm = $isTeacher || in_array('student', $roles, true);

$roleLabels = [
    'student'          => 'étudiant',
    'teacher'          => 'enseignant',
    'department_admin' => 'admin de département',
    'admin'            => 'administrateur',
];
$roleFr = static fn(string $r): string => $roleLabels[$r] ?? $r;

$themeCur = match ($user['theme'] ?? null) {
    'LIGHT' => 'light',
    'DARK'  => 'dark',
    default => 'auto',
};

/** @var array<string,mixed> $stats */
$stats   = $stats ?? [];
$fmtInt  = static fn (int $n): string => number_format($n, 0, ',', ' ');
$points  = $stats['activity'] ?? [];
$maxDay  = $points !== [] ? max(array_column($points, 'total')) : 0;
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
            <!-- Identité -->
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
                            <?php elseif ($specRequestStatus === 'pending'): ?>
                                <span class="badge badge-not-habilitated">demande en attente</span>
                            <?php elseif ($specRequestStatus === 'rejected'): ?>
                                <span class="badge badge-rejected">demande refusée</span>
                            <?php else: ?>
                                <span class="badge badge-not-habilitated">non habilité</span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($isTeacher && !$isSpecialized && in_array($specRequestStatus, ['none', 'rejected'], true)): ?>
                    <div class="spec-request">
                        <p class="section-desc">
                            <?php if ($specRequestStatus === 'rejected'): ?>
                                Votre demande précédente a été refusée. Vous pouvez en soumettre une nouvelle.
                            <?php else: ?>
                                L'habilitation vous permet d'importer vos propres modèles d'IA
                                dans vos ressources. Faites-en la demande à l'administrateur de
                                votre département.
                            <?php endif; ?>
                        </p>
                        <form method="POST" action="/profile/request-specialisation">
                            <?= csrf_field() ?>
                            <textarea name="request" rows="2" maxlength="500"
                                      placeholder="Motif de votre demande (facultatif)"></textarea>
                            <button type="submit" class="btn">
                                <?= icon('send', '', 12) ?> Demander mon habilitation
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Statistiques de consommation (profs et eleves uniquement) -->
            <?php if ($usesLlm): ?>
            <div class="dashboard-card stats-card">
                <h2><?= icon('chart-line', '', 16) ?> Ma consommation</h2>
                <p class="page-sub">Un aperçu de votre utilisation de l'assistant IA.</p>

                <div class="stats-metric-grid">
                    <article class="stats-metric">
                        <span class="stats-metric-icon"><?= icon('messages-square', '', 18) ?></span>
                        <span class="stats-metric-value"><?= $fmtInt((int) ($stats['conversations'] ?? 0)) ?></span>
                        <span class="stats-metric-label">Conversations</span>
                    </article>
                    <article class="stats-metric">
                        <span class="stats-metric-icon"><?= icon('message-circle', '', 18) ?></span>
                        <span class="stats-metric-value"><?= $fmtInt((int) ($stats['interactions'] ?? 0)) ?></span>
                        <span class="stats-metric-label">Messages envoyés</span>
                    </article>
                    <article class="stats-metric">
                        <span class="stats-metric-icon"><?= icon('arrow-down', '', 18) ?></span>
                        <span class="stats-metric-value"><?= $fmtInt((int) ($stats['input_tokens'] ?? 0)) ?></span>
                        <span class="stats-metric-label">Tokens entrée</span>
                    </article>
                    <article class="stats-metric">
                        <span class="stats-metric-icon"><?= icon('arrow-up', '', 18) ?></span>
                        <span class="stats-metric-value"><?= $fmtInt((int) ($stats['output_tokens'] ?? 0)) ?></span>
                        <span class="stats-metric-label">Tokens sortie</span>
                    </article>
                    <article class="stats-metric">
                        <span class="stats-metric-icon"><?= icon('timer', '', 18) ?></span>
                        <span class="stats-metric-value">
                            <?= $stats['avg_latency'] !== null ? $fmtInt((int) $stats['avg_latency']) . ' ms' : '—' ?>
                        </span>
                        <span class="stats-metric-label">Latence moyenne</span>
                    </article>
                </div>

                <div class="stats-activity">
                    <h3 class="stats-activity-title">
                        <?= icon('chart-line', '', 14) ?>
                        Activité (<?= (int) ($stats['activity_days'] ?? 30) ?> derniers jours)
                    </h3>
                    <?php if (($stats['activity_total'] ?? 0) > 0): ?>
                        <div class="stats-bars" role="img"
                             aria-label="Messages envoyés par jour sur les <?= (int) ($stats['activity_days'] ?? 30) ?> derniers jours">
                            <?php foreach ($points as $p): ?>
                                <?php
                                $total = (int) $p['total'];
                                $h = $maxDay > 0 ? max(3, (int) round($total / $maxDay * 100)) : 3;
                                $label = $total . ' le ' . $p['day'];
                                ?>
                                <span class="stats-bar<?= $total === 0 ? ' is-empty' : '' ?>"
                                      style="height: <?= $h ?>%" title="<?= htmlspecialchars($label) ?>"></span>
                            <?php endforeach; ?>
                        </div>
                        <p class="stats-bars-foot">
                            <?= $fmtInt((int) $stats['activity_total']) ?> message<?= (int) $stats['activity_total'] > 1 ? 's' : '' ?> sur la période
                        </p>
                    <?php else: ?>
                        <p class="no-message">Aucune activité sur la période.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Zone à risque -->
            <div class="danger-zone">
                <div class="danger-zone-header">
                    <?= icon('alert-triangle', '', 15) ?>
                    <span>Zone à risque</span>
                </div>

                <div class="danger-zone-body">

                    <!-- Désactiver le compte -->
                    <div class="danger-row">
                        <div class="danger-row-info">
                            <p class="danger-row-title">Désactiver mon compte</p>
                            <p class="danger-row-desc">
                                Rend votre compte inaccessible immédiatement. Un administrateur
                                peut le réactiver sur demande.
                            </p>
                        </div>
                        <button type="button" class="btn danger" id="btn-deactivate-account">
                            <?= icon('user-x', '', 13) ?> Désactiver
                        </button>
                    </div>

                    <div class="danger-zone-sep"></div>

                    <!-- Retirer le consentement à la recherche -->
                    <div class="danger-row">
                        <div class="danger-row-info">
                            <p class="danger-row-title">Retirer mon consentement à la recherche</p>
                            <p class="danger-row-desc">
                                Retire votre consentement au traitement de vos données pour les projets de recherche. 
                                Pour toute question, contactez&nbsp;
                                <a href="mailto:dpo@univ-amu.fr" class="danger-link">dpo@univ-amu.fr</a>.
                            </p>
                        </div>
                        <button type="button" class="btn danger" id="btn-withdraw-consent">
                            <?= icon('shield-off', '', 13) ?> Retirer le consentement
                        </button>
                    </div>

                </div>

                <div class="danger-zone-footer">
                    <a href="/rgpd_consent" class="rgpd-link">
                        <?= icon('plus', '', 12) ?> Consulter les mentions d'information RGPD
                    </a>
                </div>
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
                    <button type="submit" name="theme" value="auto"  class="theme-opt<?= $themeCur === 'auto'  ? ' is-active' : '' ?>">Automatique</button>
                    <button type="submit" name="theme" value="light" class="theme-opt<?= $themeCur === 'light' ? ' is-active' : '' ?>">Clair</button>
                    <button type="submit" name="theme" value="dark"  class="theme-opt<?= $themeCur === 'dark'  ? ' is-active' : '' ?>">Sombre</button>
                </form>
            </div>
        </aside>
    </div>
</div>

<!-- Modal : désactivation du compte -->
<div class="modal-overlay" id="modal-deactivate">
    <div class="modal-box">
        <div class="modal-icon modal-icon--danger"><?= icon('user-x', '', 22) ?></div>
        <h2>Désactiver mon compte</h2>
        <p>Êtes-vous sûr de vouloir désactiver votre compte ?</p>
        <ul class="modal-list">
            <li>Vous serez immédiatement déconnecté</li>
            <li>Vous ne pourrez plus vous connecter</li>
            <li>Vos données sont conservées ; pour les supprimer, contactez <strong>dpo@univ-amu.fr</strong></li>
        </ul>
        <form method="POST" action="/profile/deactivate" class="modal-actions">
            <?= csrf_field() ?>
            <button type="button" class="btn ghost modal-cancel" data-modal="modal-deactivate">Annuler</button>
            <button type="submit" class="btn danger">Désactiver mon compte</button>
        </form>
    </div>
</div>

<!-- Modal : retrait du consentement à la recherche -->
<div class="modal-overlay" id="modal-consent">
    <div class="modal-box">
        <div class="modal-icon modal-icon--danger"><?= icon('shield-off', '', 22) ?></div>
        <h2>Retirer mon consentement à la recherche</h2>
        <p>Cette action a des conséquences importantes :</p>
        <ul class="modal-list">
            <li>Vos données ne pourront plus être utilisées dans le cadre des projets de recherche auxquels vous avez consenti</li>
            <li>Aucune nouvelle donnée ne sera intégrée aux analyses de recherche après le traitement de votre demande</li> 
            <li>Ce processus peut prendre jusqu'à <strong>30 jours</strong></li>
        </ul>
        <form method="POST" action="/profile/withdraw-consent" class="modal-actions">
            <?= csrf_field() ?>
            <button type="button" class="btn ghost modal-cancel" data-modal="modal-consent">Annuler</button>
            <button type="submit" class="btn danger">Retirer mon consentement</button>
        </form>
    </div>
</div>

<script>
(function () {
    // Ouvrir une modal
    function openModal(id) {
        const m = document.getElementById(id);
        if (m) m.style.display = 'flex';
    }

    // Fermer une modal
    function closeModal(id) {
        const m = document.getElementById(id);
        if (m) m.style.display = 'none';
    }

    document.getElementById('btn-deactivate-account')
        ?.addEventListener('click', () => openModal('modal-deactivate'));

    document.getElementById('btn-withdraw-consent')
        ?.addEventListener('click', () => openModal('modal-consent'));

    // Boutons Annuler (data-modal="id")
    document.querySelectorAll('.modal-cancel').forEach(btn => {
        btn.addEventListener('click', () => closeModal(btn.dataset.modal));
    });

    // Clic sur le fond
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) closeModal(overlay.id);
        });
    });

    // Touche Échap
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.modal-overlay').forEach(m => {
            if (m.style.display === 'flex') closeModal(m.id);
        });
    });
})();
</script>