<?php
/**
 * One habilitated/revoked teacher table row. Shared by the dashboard view and
 * the AJAX actions (revoke / reauthorize).
 *
 * @var array<string, mixed> $teacher  Row with teacher_id, first_name, last_name, email
 * @var string $mode  'habilitated' (Revoke button) or 'revoked' (Re-habilitate button)
 */
$t = $teacher;
$isHabilitated = ($mode ?? 'habilitated') === 'habilitated';
?>
<tr data-teacher-id="<?= (int) $t['teacher_id'] ?>">
    <td><?= htmlspecialchars(trim($t['first_name'] . ' ' . $t['last_name'])) ?></td>
    <td><?= htmlspecialchars($t['email']) ?></td>
    <td>
        <?php if ($isHabilitated): ?>
            <span class="badge badge-active"><?= icon('check', '', 12) ?> Habilité</span>
        <?php else: ?>
            <span class="badge badge-draft"><?= icon('x', '', 12) ?> Révoqué</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($isHabilitated): ?>
            <form method="POST" action="/department-admin/specialisations/revoke" data-ajax-action="move-row"
                  data-confirm="Révoquer l'habilitation de cet enseignant ?">
                <?= csrf_field() ?>
                <input type="hidden" name="teacher_id" value="<?= (int) $t['teacher_id'] ?>">
                <button class="btn danger sm" type="submit"><?= icon('x', '', 13) ?> Révoquer</button>
            </form>
        <?php else: ?>
            <form method="POST" action="/department-admin/specialisations/reauthorize" data-ajax-action="move-row">
                <?= csrf_field() ?>
                <input type="hidden" name="teacher_id" value="<?= (int) $t['teacher_id'] ?>">
                <button class="btn success sm" type="submit"><?= icon('check', '', 13) ?> Réhabiliter</button>
            </form>
        <?php endif; ?>
    </td>
</tr>
