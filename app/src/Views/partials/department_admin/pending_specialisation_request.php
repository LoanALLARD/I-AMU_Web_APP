<?php
/**
 * One pending teacher habilitation request (collapsible <details>). Shared by
 * the dashboard view and the AJAX actions (approve / reject).
 *
 * @var array<string, mixed> $pending  Row with teacher_id, first_name, last_name, email, request
 */
$p = $pending;
?>
<details class="admin-pending-row" data-pending-id="spec-<?= (int) $p['teacher_id'] ?>">
    <summary>
        <span class="who">
            <span class="chevron"><?= icon('chevron-right', '', 16) ?></span>
            <span><?= htmlspecialchars(trim($p['first_name'] . ' ' . $p['last_name'])) ?> &middot; <small><?= htmlspecialchars($p['email']) ?></small></span>
        </span>
        <span class="reveal">
            <span class="reveal-open">Voir la demande <?= icon('eye', '', 13) ?></span>
            <span class="reveal-close">Masquer <?= icon('eye-off', '', 13) ?></span>
        </span>
    </summary>
    <div class="admin-pending-detail">
        <dl>
            <dt>Email</dt>
            <dd><?= htmlspecialchars($p['email']) ?></dd>
            <dt>Message</dt>
            <dd>
                <?php if (trim((string) ($p['request'] ?? '')) !== ''): ?>
                    <span class="request-text"><?= htmlspecialchars((string) $p['request']) ?></span>
                <?php else: ?>
                    <span class="no-message">Aucun message joint à la demande.</span>
                <?php endif; ?>
            </dd>
        </dl>
        <div class="admin-actions">
            <form method="POST" action="/department-admin/specialisations/approve" data-ajax-action="move-row">
                <?= csrf_field() ?>
                <input type="hidden" name="teacher_id" value="<?= (int) $p['teacher_id'] ?>">
                <button class="btn success sm" type="submit"><?= icon('check', '', 13) ?> Habiliter</button>
            </form>
            <form method="POST" action="/department-admin/specialisations/reject" data-ajax-action="move-row"
                  data-confirm="Refuser cette demande d'habilitation ?">
                <?= csrf_field() ?>
                <input type="hidden" name="teacher_id" value="<?= (int) $p['teacher_id'] ?>">
                <button class="btn danger sm" type="submit"><?= icon('x', '', 13) ?> Refuser</button>
            </form>
        </div>
    </div>
</details>
