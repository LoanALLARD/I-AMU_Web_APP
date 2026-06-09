<?php
/**
 * @var list<array<string, mixed>> $sessions    Owned sessions (SessionService::listForTeacher())
 * @var list<array<string, mixed>> $supervised  Read-only supervised sessions
 */
$supervised = $supervised ?? [];
// Type badge class → Lucide icon shown in the tinted square of each row.
$typeIcon = static fn (string $typeClass): string => [
    'badge-exam'   => 'lock',
    'badge-course' => 'book',
][$typeClass] ?? 'book';
?>
<div class="page-header">
    <div class="page-header-row" style="align-items:center;">
        <div>
            <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;">
                <h1>Mes sessions</h1>
                <span class="mono" style="font-size:11px;color:var(--gray-400);"><?= count($sessions) ?> session(s)</span>
            </div>
            <p class="page-sub" style="margin-top:6px;">Créez une session de cours ou d'examen, puis donnez le code d'accès à vos étudiants.</p>
        </div>
        <a href="/sessions/create" class="btn primary" style="margin-left:auto;">
            <?= icon('graduation-cap', '', 14) ?> Nouvelle session
        </a>
    </div>
</div>

<div class="page-body">

    <?php if ($sessions === []): ?>
        <div class="session-empty">
            <p>Aucune session pour le moment.</p>
            <a href="/sessions/create" class="btn bordered">Créer ma première session</a>
        </div>
    <?php else: ?>
        <?php $rows = $sessions; $readonly = false; require __DIR__ . '/../../partials/_session_list.php'; ?>
    <?php endif; ?>

    <?php if ($supervised !== []): ?>
        <div class="page-header" style="margin-top:28px;">
            <div class="page-header-row" style="align-items:center;">
                <div>
                    <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;">
                        <h1>Sessions surveillées</h1>
                        <span class="mono" style="font-size:11px;color:var(--gray-400);"><?= count($supervised) ?> session(s)</span>
                    </div>
                    <p class="page-sub" style="margin-top:6px;">Sessions de ressources où vous êtes enseignant responsable — lecture seule.</p>
                </div>
            </div>
        </div>

        <?php $rows = $supervised; $readonly = true; require __DIR__ . '/../../partials/_session_list.php'; ?>
    <?php endif; ?>
</div><!-- /.page-body -->
<?php /* Click-to-copy on the .access-code-cell chips is wired globally by
   /assets/js/clipboard.js (loaded in the layout). */ ?>
