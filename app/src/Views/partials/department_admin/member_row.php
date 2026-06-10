<?php
/**
 * One department member (teacher/student) table row. Shared by the dashboard
 * view and the set-active AJAX action, so the markup -- CSRF token, icons,
 * escaping, badges -- has a single source of truth and the row re-rendered
 * after an action matches the initially-rendered ones exactly.
 *
 * @var array<string, mixed> $member  Member row (users + role tables); see UserRepository::findMemberRow
 * @var int $currentUserId  The logged-in admin's id (its own row shows no action)
 */
$m = $member;
$isTeacher = $m['role'] === 'teacher';
$lastLogin = $m['last_login_at'] ?? null;
$lastLoginLabel = $lastLogin === null ? 'jamais' : date('Y-m-d H:i', strtotime((string) $lastLogin));
$isSelf = (int) $m['id'] === ($currentUserId ?? 0);

$fmtDate = static fn(?string $v): string => $v === null ? '—' : date('Y-m-d H:i', strtotime($v));
$fmtBool = static fn($v): string => $v ? 'oui' : 'non';
?>
<tr data-user-id="<?= (int) $m['id'] ?>">
    <td><?= htmlspecialchars(trim($m['first_name'] . ' ' . $m['last_name'])) ?></td>
    <td><?= htmlspecialchars($m['email']) ?></td>
    <td data-sort-value="<?= $isTeacher ? 'Enseignant' : 'Étudiant' ?>">
        <span class="badge <?= $isTeacher ? 'badge-teacher' : 'badge-student' ?>">
            <?= $isTeacher ? 'Enseignant' : 'Étudiant' ?>
        </span>
    </td>
    <td data-status-cell data-sort-value="<?= $m['is_active'] ? 'Actif' : 'Désactivé' ?>">
        <?php if ($m['is_active']): ?>
            <span class="badge badge-active"><?= icon('check', '', 12) ?> Actif</span>
        <?php else: ?>
            <span class="badge badge-draft"><?= icon('lock', '', 12) ?> Désactivé</span>
        <?php endif; ?>
    </td>
    <td class="mono" data-sort-value="<?= htmlspecialchars((string) ($lastLogin ?? '')) ?>"><?= htmlspecialchars($lastLoginLabel) ?></td>
    <td data-action-cell>
        <button type="button" class="badge badge-info" data-member-info
                data-member-name="<?= htmlspecialchars(trim($m['first_name'] . ' ' . $m['last_name'])) ?>">
            <?= icon('eye', '', 12) ?> infos
        </button>
        <template data-member-info-panel>
            <div class="member-info-panel">
                <dl>
                    <dt>Créé le</dt>
                    <dd><?= htmlspecialchars($fmtDate($m['created_at'] ?? null)) ?></dd>
                    <dt>Dernière connexion</dt>
                    <dd><?= htmlspecialchars($lastLoginLabel) ?></dd>
                    <dt>Email vérifié</dt>
                    <dd><?= htmlspecialchars($fmtDate($m['email_verified_at'] ?? null)) ?></dd>
                    <dt>Consentement RGPD</dt>
                    <dd><?= htmlspecialchars($fmtDate($m['consent_at'] ?? null)) ?><?= !empty($m['consent_version']) ? ' (' . htmlspecialchars((string) $m['consent_version']) . ')' : '' ?></dd>
                    <dt>Opposition recherche</dt>
                    <dd><?= htmlspecialchars($fmtBool($m['research_opposed'] ?? false)) ?></dd>
                    <?php if ($isTeacher): ?>
                        <dt>Titre</dt>
                        <dd><?= htmlspecialchars((string) ($m['title'] ?? '—')) ?></dd>
                        <dt>Habilité</dt>
                        <dd><?= htmlspecialchars($fmtBool($m['is_specialised'] ?? false)) ?></dd>
                    <?php else: ?>
                        <dt>N° étudiant</dt>
                        <dd><?= htmlspecialchars((string) ($m['student_number'] ?? '—')) ?></dd>
                        <dt>Année</dt>
                        <dd><?= htmlspecialchars((string) ($m['year'] ?? '—')) ?></dd>
                    <?php endif; ?>
                </dl>
                <?php if (!$isSelf): ?>
                    <form method="POST" action="/department-admin/users/set-active" data-ajax-action="set-active">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                        <input type="hidden" name="activate" value="<?= $m['is_active'] ? '0' : '1' ?>">
                        <?php if ($m['is_active']): ?>
                            <button class="btn danger sm" type="submit"><?= icon('lock', '', 13) ?> Désactiver</button>
                        <?php else: ?>
                            <button class="btn success sm" type="submit"><?= icon('check', '', 13) ?> Réactiver</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </template>
    </td>
</tr>
