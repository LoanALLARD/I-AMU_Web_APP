<?php
/**
 * One pending researcher request (a collapsible <details> block). Shared by the
 * dashboard view and the AJAX actions (approve / reject), so the markup -- CSRF
 * token, icons, escaping -- has a single source of truth.
 *
 * @var array<string, mixed> $pending  Row with first_name, last_name, email, lab_code, lab_name, request, researcher_id
 */
$p = $pending;
?>
<details class="admin-pending-row" data-pending-id="<?= (int) $p['researcher_id'] ?>">
    <summary>
        <span class="who">
            <span class="chevron"><?= icon('chevron-right', '', 16) ?></span>
            <span><?= htmlspecialchars(trim($p['first_name'] . ' ' . $p['last_name'])) ?> &middot; <small><?= htmlspecialchars($p['email']) ?> &middot; labo <?= htmlspecialchars($p['lab_code']) ?></small></span>
        </span>
        <span class="reveal">
            <span class="reveal-open">Voir la demande <?= icon('eye', '', 13) ?></span>
            <span class="reveal-close">Masquer <?= icon('eye-off', '', 13) ?></span>
        </span>
    </summary>
    <div class="admin-pending-detail">
        <dl>
            <dt>Laboratoire</dt>
            <dd><?= htmlspecialchars($p['lab_name']) ?> (<?= htmlspecialchars($p['lab_code']) ?>)</dd>
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
            <form method="POST" action="/department-admin/researchers/approve" data-ajax-action="move-row">
                <?= csrf_field() ?>
                <input type="hidden" name="researcher_id" value="<?= (int) $p['researcher_id'] ?>">
                <button class="btn success sm" type="submit"><?= icon('check', '', 13) ?> Valider</button>
            </form>
            <form method="POST" action="/department-admin/researchers/reject" data-ajax-action="move-row"
                  data-confirm="Refuser cette demande chercheur ?">
                <?= csrf_field() ?>
                <input type="hidden" name="researcher_id" value="<?= (int) $p['researcher_id'] ?>">
                <button class="btn danger sm" type="submit"><?= icon('x', '', 13) ?> Refuser</button>
            </form>
        </div>
    </div>
</details>
