<?php
/**
 * Researcher space: file an access request for a department and review the
 * status of existing requests.
 *
 * @var array{id:int, email:string, first_name:string, last_name:string, roles:list<string>, department_id:int|null}|null $user
 * @var list<array{id:int, name:string}> $places
 * @var list<array<string, mixed>> $requests
 */
$places   = $places ?? [];
$requests = $requests ?? [];
$activeTab = 'access';

// Split by status into three sections so a refusal never reads as pending.
$granted = array_filter($requests, static fn (array $r): bool => $r['status'] === 'authorized');
$pending = array_filter($requests, static fn (array $r): bool => $r['status'] === 'pending');
$history = array_filter($requests, static fn (array $r): bool => in_array($r['status'], ['rejected', 'revoked'], true));

// Status -> (French label, badge CSS class). Mirrors the service's deriveStatus.
$statusMeta = [
    'pending'    => ['En attente', 'badge-scheduled'],
    'authorized' => ['Accès accordé', 'badge-active'],
    'rejected'   => ['Refusée', 'badge-cancelled'],
    'revoked'    => ['Accès révoqué', 'badge-cancelled'],
];
require __DIR__ . '/_header.php';
?>
<div class="page-body">

    <?php /* Flash messages are rendered once by the chat layout (partials/_flash.php). */ ?>

    <div class="admin-section">
        <h2><?= icon('building', '', 16) ?> Mes accès</h2>

        <?php if ($granted === []): ?>
            <p class="no-message">Vous n'avez accès aux données d'aucun département pour le moment.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Département</th>
                        <th>Lieu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($granted as $g): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $g['department_name']) ?></td>
                            <td><?= htmlspecialchars((string) $g['place_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="admin-section">
        <h2><?= icon('book', '', 16) ?> Demander l'accès à un département</h2>
        <p class="page-sub">Choisissez le département dont vous souhaitez consulter les données. Votre demande sera traitée par l'administrateur de ce département. Une seule demande par département.</p>

        <form method="POST" action="/researcher/requests" class="researcher-request-form">
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="place_id">Lieu</label>
                    <select id="place_id" name="place_id" required>
                        <option value="">Choisir un lieu…</option>
                        <?php foreach ($places as $place): ?>
                            <option value="<?= (int) $place['id'] ?>"><?= htmlspecialchars($place['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="department_id">Département</label>
                    <select id="department_id" name="department_id" required disabled data-selected="">
                        <option value="">Choisir d'abord un lieu…</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="request">Message (optionnel)</label>
                <textarea id="request" name="request" rows="3"
                          placeholder="Précisez l'objet de votre demande (étude, données concernées…)."></textarea>
            </div>

            <button type="submit" class="btn success"><?= icon('check', '', 14) ?> Envoyer la demande</button>
        </form>
    </div>

    <div class="admin-section">
        <h2><?= icon('eye', '', 16) ?> Demandes en attente</h2>

        <?php if ($pending === []): ?>
            <p class="no-message">Aucune demande en attente.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Département</th>
                        <th>Lieu</th>
                        <th>Message</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $req): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $req['department_name']) ?></td>
                            <td><?= htmlspecialchars((string) $req['place_name']) ?></td>
                            <td>
                                <?php if (trim((string) ($req['request'] ?? '')) !== ''): ?>
                                    <?= htmlspecialchars((string) $req['request']) ?>
                                <?php else: ?>
                                    <span class="no-message">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="/researcher/requests/cancel"
                                      data-confirm="Annuler cette demande d'accès ?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="department_id" value="<?= (int) $req['department_id'] ?>">
                                    <button class="btn danger sm" type="submit"><?= icon('x', '', 13) ?> Annuler</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($history !== []): ?>
        <div class="admin-section">
            <h2><?= icon('book', '', 16) ?> Historique</h2>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Département</th>
                        <th>Lieu</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $req): ?>
                        <?php [$label, $badgeClass] = $statusMeta[$req['status']] ?? ['Inconnu', 'badge-draft']; ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $req['department_name']) ?></td>
                            <td><?= htmlspecialchars((string) $req['place_name']) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($label) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php
$jsPath = __DIR__ . '/../../../../public/assets/js/register.js';
$jsVer  = is_file($jsPath) ? filemtime($jsPath) : 0;
?>
<script src="/assets/js/register.js?v=<?= $jsVer ?>" defer></script>
