<?php
/**
 * One department member (teacher/student) table row. Shared by the dashboard
 * view and the set-active AJAX action, so the markup -- CSRF token, icons,
 * escaping, badges -- has a single source of truth and the row re-rendered
 * after an action matches the initially-rendered ones exactly.
 *
 * @var array<string, mixed> $member  Row with id, first_name, last_name, email, role, is_active
 * @var int $currentUserId  The logged-in admin's id (its own row shows no action)
 */
$m = $member;
$isTeacher = $m['role'] === 'teacher';
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
    <td data-action-cell>
        <?php if ((int) $m['id'] !== ($currentUserId ?? 0)): ?>
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
    </td>
</tr>
