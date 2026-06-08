<?php
/**
 * One researcher table row. Shared by the dashboard view and the AJAX actions
 * (revoke / reauthorize), so the markup -- CSRF token, icons, escaping -- has a
 * single source of truth.
 *
 * @var array<string, mixed> $researcher  Row with first_name, last_name, email, lab_name, lab_code, researcher_id
 * @var string $mode  'authorized' (active access, Revoke button) or 'revoked' (Reauthorize button)
 */
$r = $researcher;
$isAuthorized = ($mode ?? 'authorized') === 'authorized';
?>
<tr data-researcher-id="<?= (int) $r['researcher_id'] ?>">
    <td><?= htmlspecialchars(trim($r['first_name'] . ' ' . $r['last_name'])) ?></td>
    <td><?= htmlspecialchars($r['email']) ?></td>
    <td><?= htmlspecialchars($r['lab_name']) ?> (<?= htmlspecialchars($r['lab_code']) ?>)</td>
    <td>
        <?php if ($isAuthorized): ?>
            <span class="badge badge-active"><?= icon('check', '', 12) ?> Autorisé</span>
        <?php else: ?>
            <span class="badge badge-draft"><?= icon('x', '', 12) ?> Révoqué</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($isAuthorized): ?>
            <form method="POST" action="/department-admin/researchers/revoke" data-ajax-action="move-row"
                  data-confirm="Révoquer l'accès de ce chercheur à votre département ?">
                <?= csrf_field() ?>
                <input type="hidden" name="researcher_id" value="<?= (int) $r['researcher_id'] ?>">
                <button class="btn danger sm" type="submit"><?= icon('x', '', 13) ?> Révoquer l'accès</button>
            </form>
        <?php else: ?>
            <form method="POST" action="/department-admin/researchers/reauthorize" data-ajax-action="move-row">
                <?= csrf_field() ?>
                <input type="hidden" name="researcher_id" value="<?= (int) $r['researcher_id'] ?>">
                <button class="btn success sm" type="submit"><?= icon('check', '', 13) ?> Réautoriser</button>
            </form>
        <?php endif; ?>
    </td>
</tr>
