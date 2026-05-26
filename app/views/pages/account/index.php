<?php
// Construction du libellé de rôle "principal" pour affichage
$roleLabel = '—';
if (!empty($roles)) {
    $map = [
        'student'             => 'Étudiant·e',
        'teacher'             => 'Enseignant·e',
        'teacher_specialised' => 'Enseignant·e spécialisé·e',
        'researcher'          => 'Chercheur·euse',
        'admin'               => 'Administrateur·trice',
    ];
    $roleLabel = implode(' · ', array_map(
        fn($r) => $map[$r] ?? ucfirst($r),
        $roles
    ));
}

// Formate la taille en octets
$formatBytes = function (int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1, ',', ' ') . ' KB';
    return number_format($bytes / 1048576, 1, ',', ' ') . ' MB';
};
?>
<div class="page-container account-page">
    <div class="page-header">
        <h1>Mon compte</h1>
        <p class="page-subtitle">Préférences locales et données personnelles.</p>
    </div>

    <div class="account-layout">
        <!-- ─── Sidebar interne ─────────────────────────────── -->
        <aside class="account-nav">
            <a href="#profil"          class="account-nav-item active">Profil</a>
            <a href="#apparence"       class="account-nav-item">Apparence</a>
            <a href="#donnees"         class="account-nav-item">
                Données
                <span class="account-nav-meta">
                    <?= number_format($stats['conversations'], 0, ',', ' ') ?> conv ·
                    <?= $formatBytes($stats['bytes']) ?>
                </span>
            </a>
            <a href="#recherche"       class="account-nav-item">Consentement recherche</a>
            <a href="#zone-risquee"    class="account-nav-item account-nav-danger">Zone risquée</a>
        </aside>

        <!-- ─── Contenu ─────────────────────────────────────── -->
        <div class="account-content">

            <!-- Profil -->
            <section id="profil" class="account-section">
                <div class="account-section-head">
                    <h2>Profil</h2>
                </div>
                <form method="POST" action="/account/profile" class="account-form">
                    <div class="account-row">
                        <label class="account-label">Identifiant</label>
                        <div class="account-value">
                            <?= htmlspecialchars($account['last_name']) ?>, <?= htmlspecialchars($account['first_name']) ?>
                            <?php if ($studentNumber): ?>
                                <span class="account-value-meta"> · <?= htmlspecialchars($studentNumber) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="account-row">
                        <label class="account-label">Rôle</label>
                        <div class="account-value"><?= htmlspecialchars($roleLabel) ?></div>
                    </div>

                    <div class="account-row">
                        <label class="account-label" for="email">Email</label>
                        <div class="account-value">
                            <input type="email" id="email" value="<?= htmlspecialchars($account['email']) ?>" disabled class="account-input">
                            <small class="form-hint">L'email ne peut pas être modifié.</small>
                        </div>
                    </div>

                    <!-- Prénom / Nom éditables, repliés en row standard -->
                    <div class="account-row">
                        <label class="account-label" for="first_name">Prénom</label>
                        <div class="account-value">
                            <input type="text" id="first_name" name="first_name" class="account-input"
                                   value="<?= htmlspecialchars($account['first_name']) ?>" required>
                        </div>
                    </div>
                    <div class="account-row">
                        <label class="account-label" for="last_name">Nom</label>
                        <div class="account-value">
                            <input type="text" id="last_name" name="last_name" class="account-input"
                                   value="<?= htmlspecialchars($account['last_name']) ?>" required>
                        </div>
                    </div>

                    <div class="account-row">
                        <label class="account-label">Membre depuis</label>
                        <div class="account-value account-value-muted">
                            <?= date('d/m/Y', strtotime($account['created_at'])) ?>
                        </div>
                    </div>

                    <div class="account-actions">
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </section>

            <!-- Apparence -->
            <section id="apparence" class="account-section">
                <div class="account-section-head">
                    <h2>Apparence</h2>
                </div>

                <div class="account-row">
                    <label class="account-label">Thème</label>
                    <div class="account-value">
                        <div class="seg-toggle" data-pref="theme">
                            <button type="button" class="seg-btn" data-value="light">Clair</button>
                            <button type="button" class="seg-btn" data-value="dark">Sombre</button>
                            <button type="button" class="seg-btn" data-value="auto">Auto</button>
                        </div>
                    </div>
                </div>

                <div class="account-row">
                    <label class="account-label">Densité</label>
                    <div class="account-value">
                        <div class="seg-toggle" data-pref="density">
                            <button type="button" class="seg-btn" data-value="compact">Compact</button>
                            <button type="button" class="seg-btn" data-value="normal">Normal</button>
                            <button type="button" class="seg-btn" data-value="airy">Aéré</button>
                        </div>
                    </div>
                </div>

                <div class="account-row">
                    <label class="account-label">Langue</label>
                    <div class="account-value">
                        <div class="seg-toggle" data-pref="lang">
                            <button type="button" class="seg-btn" data-value="fr">FR</button>
                            <button type="button" class="seg-btn" data-value="en">EN</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Données -->
            <section id="donnees" class="account-section">
                <div class="account-section-head">
                    <h2>Données · <?= number_format($stats['conversations'], 0, ',', ' ') ?> conversations · <?= $formatBytes($stats['bytes']) ?></h2>
                </div>

                <div class="account-row">
                    <label class="account-label">Conversations</label>
                    <div class="account-value"><?= number_format($stats['conversations'], 0, ',', ' ') ?></div>
                </div>
                <div class="account-row">
                    <label class="account-label">Échanges (prompts + réponses)</label>
                    <div class="account-value"><?= number_format($stats['interactions'] * 2, 0, ',', ' ') ?> messages</div>
                </div>
                <div class="account-row">
                    <label class="account-label">Stockage occupé</label>
                    <div class="account-value"><?= $formatBytes($stats['bytes']) ?></div>
                </div>
                <div class="account-row">
                    <label class="account-label">Export</label>
                    <div class="account-value">
                        <a href="/export/json" class="btn btn-secondary btn-sm">Télécharger mes données (JSON)</a>
                    </div>
                </div>
            </section>

            <!-- Consentement recherche -->
            <section id="recherche" class="account-section">
                <div class="account-section-head">
                    <h2>Consentement recherche</h2>
                </div>

                <p class="account-help">
                    Mes prompts et réponses peuvent être utilisés, sous forme anonymisée, dans le cadre
                    de la recherche universitaire menée par I-AMU. Vous pouvez retirer ce consentement
                    à tout moment.
                </p>

                <div class="account-row">
                    <label class="account-label">État</label>
                    <div class="account-value">
                        <?php if ($account['gdpr_consent']): ?>
                            <?= icon('check', 'text-success') ?> Accepté
                            <?php if ($account['gdpr_consent_at']): ?>
                                <span class="account-value-meta"> · le <?= date('d/m/Y à H:i', strtotime($account['gdpr_consent_at'])) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= icon('x', 'text-error') ?> Refusé
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($account['gdpr_consent']): ?>
                    <form method="POST" action="/account/revoke-consent">
                        <button type="submit" class="btn btn-secondary"
                                onclick="return confirm('Retirer votre consentement vous déconnectera de la plateforme. Continuer ?')">
                            Retirer mon consentement
                        </button>
                    </form>
                <?php endif; ?>
            </section>

            <!-- Zone risquée -->
            <section id="zone-risquee" class="account-section account-section-danger">
                <div class="account-section-head">
                    <h2>Zone risquée</h2>
                </div>

                <!-- Changer mot de passe -->
                <details class="account-collapsible">
                    <summary>Changer le mot de passe</summary>
                    <form method="POST" action="/account/password" class="account-form">
                        <div class="account-row">
                            <label class="account-label" for="current_password">Mot de passe actuel</label>
                            <div class="account-value">
                                <input type="password" id="current_password" name="current_password" required class="account-input">
                            </div>
                        </div>
                        <div class="account-row">
                            <label class="account-label" for="new_password">Nouveau mot de passe</label>
                            <div class="account-value">
                                <input type="password" id="new_password" name="new_password" required minlength="8" class="account-input">
                            </div>
                        </div>
                        <div class="account-row">
                            <label class="account-label" for="confirm_password">Confirmation</label>
                            <div class="account-value">
                                <input type="password" id="confirm_password" name="confirm_password" required class="account-input">
                            </div>
                        </div>
                        <div class="account-actions">
                            <button type="submit" class="btn btn-primary">Modifier le mot de passe</button>
                        </div>
                    </form>
                </details>

                <!-- Suppression -->
                <details class="account-collapsible">
                    <summary>Supprimer définitivement mon compte</summary>
                    <p class="account-help">
                        Cette action est irréversible. Toutes vos données seront désactivées.
                        Les exports de recherche déjà effectués restent anonymisés conformément au RGPD.
                    </p>
                    <form method="POST" action="/account/delete" class="account-form">
                        <div class="account-row">
                            <label class="account-label" for="delete_password">Mot de passe</label>
                            <div class="account-value">
                                <input type="password" id="delete_password" name="delete_password" required class="account-input">
                            </div>
                        </div>
                        <div class="account-actions">
                            <button type="submit" class="btn btn-danger"
                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer votre compte ?')">
                                Supprimer définitivement
                            </button>
                        </div>
                    </form>
                </details>
            </section>

        </div>
    </div>
</div>

<script>
// ─── Préférences locales (apparence) ─────────────────────────────
(function() {
    const PREFS = ['theme', 'density', 'lang'];
    const DEFAULTS = { theme: 'light', density: 'normal', lang: 'fr' };

    function get(key) { return localStorage.getItem('iamu-' + key) || DEFAULTS[key]; }
    function set(key, val) { localStorage.setItem('iamu-' + key, val); applyPref(key, val); }

    function applyPref(key, val) {
        if (key === 'theme') {
            if (val === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
            else if (val === 'light') document.documentElement.removeAttribute('data-theme');
            else { // auto
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.toggleAttribute('data-theme', prefersDark);
                if (prefersDark) document.documentElement.setAttribute('data-theme', 'dark');
            }
        } else if (key === 'density') {
            document.documentElement.setAttribute('data-density', val);
        } else if (key === 'lang') {
            document.documentElement.setAttribute('lang', val);
        }
    }

    // Active les boutons selon la valeur courante + branche le click
    document.querySelectorAll('.seg-toggle').forEach(toggle => {
        const key = toggle.dataset.pref;
        const current = get(key);
        toggle.querySelectorAll('.seg-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.value === current);
            btn.addEventListener('click', () => {
                toggle.querySelectorAll('.seg-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                set(key, btn.dataset.value);
            });
        });
        applyPref(key, current);
    });
})();

// ─── Highlight de l'item actif au scroll ─────────────────────────
(function() {
    const links = document.querySelectorAll('.account-nav-item');
    const sections = Array.from(document.querySelectorAll('.account-section'));

    function highlight() {
        const y = window.scrollY + 120;
        let activeId = sections[0]?.id;
        for (const s of sections) {
            if (s.offsetTop <= y) activeId = s.id;
        }
        links.forEach(l => l.classList.toggle('active', l.getAttribute('href') === '#' + activeId));
    }

    window.addEventListener('scroll', highlight, { passive: true });
    highlight();
})();
</script>
