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
$specRequestStatus = $specRequestStatus ?? 'none';
$roles = $user['roles'] ?? [];
$isTeacher = in_array('teacher', $roles, true);
$isAdmin = in_array('admin', $roles, true) || in_array('department_admin', $roles, true);
$isResearcher = in_array('researcher', $roles, true);
$isSpecialized = !empty($user['isSpecialized']);
// Only teachers and students use the LLM, so only they get usage stats.
$usesLlm = $isTeacher || in_array('student', $roles, true);

$roleLabels = [
    'student'          => 'étudiant',
    'teacher'          => 'enseignant',
    'researcher'       => 'chercheur',
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

// Energy footprint, with unit promotion (Wh -> kWh, g -> kg) for readability.
$energy = $stats['energy'] ?? ['wh' => 0.0, 'gco2' => 0.0, 'eq_car_km' => 0.0, 'eq_phone_charges' => 0.0, 'eq_led_hours' => 0.0];
$ef = $stats['energy_factors'] ?? ['wh_per_output_token' => 0.0, 'input_token_weight' => 0.0, 'reference_size_b' => 0.0, 'grid_gco2_per_kwh' => 0.0];
$fmtNum = static fn (float $n, int $d = 1): string => number_format($n, $d, ',', ' ');
$fmtEnergy = static function (float $wh) use ($fmtNum): string {
    return $wh >= 1000 ? $fmtNum($wh / 1000, 2) . ' kWh' : $fmtNum($wh, 1) . ' Wh';
};
$fmtCo2 = static function (float $g) use ($fmtNum): string {
    return $g >= 1000 ? $fmtNum($g / 1000, 2) . ' kg' : $fmtNum($g, 1) . ' g';
};

// "aujourd'hui" / "hier" / "il y a N jours" from a stored timestamp.
$relativeDay = static function (?string $ts): string {
    if ($ts === null) {
        return 'jamais';
    }
    $days = (int) (new \DateTimeImmutable('today'))
        ->diff((new \DateTimeImmutable($ts))->setTime(0, 0))
        ->format('%r%a');
    return match (true) {
        $days >= 0  => "aujourd'hui",
        $days === -1 => 'hier',
        default     => 'il y a ' . abs($days) . ' jours',
    };
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
                    <div class="card-actions">
                        <button type="button" class="btn" id="btn-toggle-edit">
                            <?= icon('edit', '', 14) ?> Modifier mes informations
                        </button>
                    </div>
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
            <div class="dashboard-card edit-form-card" id="edit-form-card">
                    <h2>Modifier mes informations</h2>
                    <form method="POST" action="/profile/update" class="edit-profile-form">
                        <?= csrf_field() ?>
                        <?php if ($user['roles'][0] == "teacher"): ?>
                        <div class="form-group full-width">
                            <label for="title" placeholder="ex : Maitre de conférences">Votre titre<span class="hint" ></span></label>
                            <input type="text" id="title" name="title">
                        </div>
                        <?php endif ?>


                        <div class="form-group full-width">
                            <label for="password">Nouveau mot de passe <span class="hint">(laisser vide pour ne pas modifier)</span></label>
                            <input type="password" id="password" name="password"
                                   placeholder="Min. 12 caractères, 1 majuscule, 1 chiffre, 1 caractère spécial"
                                   minlength="12"
                                   pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,}"
                                   title="Au moins 12 caractères, une majuscule, un chiffre et un caractère spécial.">
                        </div>

                        <div class="form-group full-width">
                            <label for="password_confirm">Confirmer le mot de passe</label>
                            <input type="password" id="password_confirm" name="password_confirm">
                        </div>

                        <div class="form-group full-width">
                            <label>
                                <input type="checkbox" id="toggle-password-visibility"> Afficher les mots de passe
                            </label>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn ghost" id="btn-cancel-edit">Annuler</button>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
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
                        <span class="stats-metric-icon"><?= icon('copy', '', 18) ?></span>
                        <span class="stats-metric-value">
                            <?= $fmtInt((int) ($stats['input_tokens'] ?? 0) + (int) ($stats['output_tokens'] ?? 0)) ?>
                        </span>
                        <span class="stats-metric-label">Tokens échangés</span>
                    </article>
                    <article class="stats-metric">
                        <span class="stats-metric-icon"><?= icon('database', '', 18) ?></span>
                        <span class="stats-metric-value stats-metric-value--text">
                            <?= htmlspecialchars($stats['top_model'] ?? '—') ?>
                        </span>
                        <span class="stats-metric-label">Modèle le plus utilisé</span>
                    </article>
                    <article class="stats-metric">
                        <span class="stats-metric-icon"><?= icon('messages-square', '', 18) ?></span>
                        <span class="stats-metric-value">
                            <?php $mpc = $stats['messages_per_conversation'] ?? null; ?>
                            <?= $mpc !== null ? number_format((float) $mpc, 1, ',', ' ') : '—' ?>
                        </span>
                        <span class="stats-metric-label">Messages / conversation</span>
                    </article>
                    <article class="stats-metric">
                        <span class="stats-metric-icon"><?= icon('clock', '', 18) ?></span>
                        <span class="stats-metric-value stats-metric-value--text">
                            <?= htmlspecialchars($relativeDay($stats['last_activity'] ?? null)) ?>
                        </span>
                        <span class="stats-metric-label">Dernière activité</span>
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

            <!-- Empreinte ecologique -->
            <div class="dashboard-card eco-card">
                <h2><?= icon('flask-conical', '', 16) ?> Empreinte écologique</h2>
                <p class="page-sub">
                    Estimation indicative de l'impact de vos échanges avec l'IA.
                    Chaque requête consomme de l'électricité&nbsp;: ces chiffres aident à en prendre conscience.
                </p>

                <div class="eco-figures">
                    <div class="eco-figure">
                        <span class="eco-figure-icon"><?= icon('database', '', 18) ?></span>
                        <span class="eco-figure-value"><?= htmlspecialchars($fmtEnergy((float) $energy['wh'])) ?></span>
                        <span class="eco-figure-label">Énergie estimée</span>
                    </div>
                    <div class="eco-figure">
                        <span class="eco-figure-icon"><?= icon('flask-conical', '', 18) ?></span>
                        <span class="eco-figure-value"><?= htmlspecialchars($fmtCo2((float) $energy['gco2'])) ?> CO₂</span>
                        <span class="eco-figure-label">Émissions estimées</span>
                    </div>
                </div>

                <ul class="eco-equivalents">
                    <li>
                        <?= icon('chart-line', '', 13) ?>
                        soit environ <strong><?= htmlspecialchars($fmtNum((float) $energy['eq_car_km'], 2)) ?>&nbsp;km</strong> en voiture
                    </li>
                    <li>
                        <?= icon('database', '', 13) ?>
                        ou <strong><?= htmlspecialchars($fmtNum((float) $energy['eq_phone_charges'], 1)) ?></strong> recharge(s) de téléphone
                    </li>
                    <li>
                        <?= icon('clock', '', 13) ?>
                        ou une ampoule LED allumée <strong><?= htmlspecialchars($fmtNum((float) $energy['eq_led_hours'], 1)) ?>&nbsp;h</strong>
                    </li>
                </ul>

                <div class="eco-tips">
                    <p class="eco-tips-title"><?= icon('check', '', 13) ?> Réduire son empreinte</p>
                    <ul>
                        <li>Privilégiez des prompts clairs et concis : moins de tokens, moins d'énergie.</li>
                        <li>Réutilisez une conversation existante plutôt que d'en relancer une à chaque question.</li>
                        <li>Pour les tâches simples, un modèle plus léger suffit souvent.</li>
                    </ul>
                </div>

                <details class="eco-method">
                    <summary>
                        <?= icon('alert-triangle', '', 12) ?>
                        Comment ce chiffre est-il calculé&nbsp;?
                    </summary>
                    <div class="eco-method-body">
                        <p>
                            Le calcul part du nombre de <strong>tokens</strong> (unités de texte) échangés,
                            la seule donnée réellement mesurée&nbsp;:
                        </p>
                        <ol>
                            <li>
                                Les tokens <em>générés</em> par l'IA pèsent plus que ceux de votre question&nbsp;:
                                produire une réponse fait travailler le modèle à chaque mot, alors que lire votre
                                message ne se fait qu'une fois.
                            </li>
                            <li>
                                Plus le modèle est gros (en milliards de paramètres), plus il consomme par token.
                                Le coût est ajusté selon la taille du modèle utilisé.
                            </li>
                            <li>
                                On en déduit une <strong>énergie</strong> (Wh), convertie en <strong>CO₂</strong>
                                via l'intensité carbone du réseau électrique (~50&nbsp;g/kWh, mix français).
                            </li>
                        </ol>
                        <p>En clair, avec les facteurs actuels&nbsp;:</p>
                        <pre class="eco-formula"><code>énergie (Wh) = Σ (tokens_sortie + <?= htmlspecialchars(rtrim(rtrim(number_format((float) $ef['input_token_weight'], 2, '.', ''), '0'), '.')) ?> × tokens_entrée)
               × <?= htmlspecialchars(rtrim(rtrim(number_format((float) $ef['wh_per_output_token'], 6, '.', ''), '0'), '.')) ?> Wh/token
               × (taille_modèle_B ÷ <?= htmlspecialchars(rtrim(rtrim(number_format((float) $ef['reference_size_b'], 1, '.', ''), '0'), '.')) ?>)

CO₂ (g)      = énergie ÷ 1000 × <?= htmlspecialchars(rtrim(rtrim(number_format((float) $ef['grid_gco2_per_kwh'], 1, '.', ''), '0'), '.')) ?> g/kWh</code></pre>
                        <p class="eco-formula-note">
                            La somme (Σ) additionne chaque modèle séparément, car un modèle plus gros
                            consomme davantage par token.
                        </p>

                        <p class="eco-method-limit">
                            <strong>Limites&nbsp;:</strong> il s'agit d'une estimation, pas d'une mesure.
                            La consommation réelle dépend du matériel (GPU), du refroidissement des serveurs
                            et de l'électricité réellement utilisée, que nous ne mesurons pas. Ces valeurs ne
                            sont fournies qu'à titre indicatif, pour donner un ordre de grandeur.
                        </p>
                    </div>
                </details>
            </div>
            <?php endif; ?>
            <?php if (!$isAdmin): ?>
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
                    <?php if (!$isAdmin && !$isResearcher): ?>
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
                    <?php endif; ?>

                </div>

                <div class="danger-zone-footer">
                    <a href="/gdpr_consent" class="gdpr-link">
                        <?= icon('plus', '', 12) ?> Consulter les mentions d'information RGPD
                    </a>
                </div>
            </div>
            <?php endif; ?>
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
document.addEventListener('DOMContentLoaded', () => {
    const btnToggleEdit = document.getElementById('btn-toggle-edit');
    const btnCancelEdit = document.getElementById('btn-cancel-edit');
    const editFormCard = document.getElementById('edit-form-card');

    btnToggleEdit?.addEventListener('click', (e) => {
        e.preventDefault();
        
        editFormCard.classList.toggle('is-open');
        
        if (editFormCard.classList.contains('is-open')) {
            setTimeout(() => {
                editFormCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 300);
        }
    });

    btnCancelEdit?.addEventListener('click', () => {
        editFormCard.classList.remove('is-open');
    });
});
const togglePasswordVisibility = document.getElementById('toggle-password-visibility');
const passwordInput = document.getElementById('password');
const passwordConfirmInput = document.getElementById('password_confirm');

togglePasswordVisibility?.addEventListener('change', () => {
    const isChecked = togglePasswordVisibility.checked;
    passwordInput.type = isChecked ? 'text' : 'password';
    passwordConfirmInput.type = isChecked ? 'text' : 'password';
});
</script>