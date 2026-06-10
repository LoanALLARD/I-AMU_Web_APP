<?php
/**
 * Researcher analysis: site/department accordion on the left, AJAX dashboard on the right.
 *
 * @var array{id:int, email:string, first_name:string, last_name:string, roles:list<string>, department_id:int|null}|null $user
 * @var list<array{place_id:int, place_name:string, departments:list<array{department_id:int, department_name:string}>}> $places
 */
$places    = $places ?? [];
$activeTab = 'analysis';
require __DIR__ . '/_header.php';
?>
<div class="page-body">

    <div class="admin-section">
        <h2><?= icon('chart-line', '', 16) ?> Analyse</h2>

        <?php if ($places === []): ?>
            <p class="no-message">Vous n'avez accès aux données d'aucun département pour le moment. Demandez un accès depuis l'onglet « Mes accès ».</p>
        <?php else: ?>
            <div class="analysis-layout">

                <!-- Left column: accordion of sites + their departments -->
                <aside class="analysis-sites">
                    <p class="section-lead">Sélectionnez un site ou un département à analyser.</p>

                    <ul class="site-accordion">
                        <?php foreach ($places as $place): ?>
                            <?php
                            $deptCount = count($place['departments']);
                            // A site is selectable only when it groups several departments.
                            $siteSelectable = $deptCount > 1;
                            $deptIds = implode(',', array_map(
                                static fn (array $d): int => (int) $d['department_id'],
                                $place['departments']
                            ));
                            ?>
                            <li class="site-group" data-site>
                                <button type="button"
                                        class="site-toggle<?= $siteSelectable ? ' is-selectable' : '' ?>"
                                        aria-expanded="false"
                                        <?= $siteSelectable ? 'data-site-id="' . (int) $place['place_id'] . '"' : '' ?>
                                        data-department-ids="<?= htmlspecialchars($deptIds, ENT_QUOTES) ?>"
                                        data-place-name="<?= htmlspecialchars($place['place_name'], ENT_QUOTES) ?>">
                                    <span class="site-name">
                                        <?= icon('building', 'site-icon', 16) ?>
                                        <span class="site-label"><?= htmlspecialchars($place['place_name']) ?></span>
                                    </span>
                                    <span class="site-meta">
                                        <?php if ($siteSelectable): ?>
                                            <span class="site-action"><?= icon('chart-line', '', 13) ?> Vue d'ensemble</span>
                                        <?php endif; ?>
                                        <span class="site-count" title="<?= $deptCount ?> département(s) accessible(s)"><?= $deptCount ?></span>
                                        <?= icon('chevron-right', 'site-chevron', 18) ?>
                                    </span>
                                </button>
                                <div class="dept-panel" hidden>
                                    <?php if ($siteSelectable): ?>
                                        <p class="dept-hint">Cliquez le site pour une vue d'ensemble, ou un département.</p>
                                    <?php endif; ?>
                                    <ul class="dept-list">
                                        <?php foreach ($place['departments'] as $dept): ?>
                                            <li>
                                                <button type="button" class="dept-item"
                                                        data-department-id="<?= (int) $dept['department_id'] ?>"
                                                        data-department-name="<?= htmlspecialchars($dept['department_name'], ENT_QUOTES) ?>"
                                                        data-place-name="<?= htmlspecialchars($place['place_name'], ENT_QUOTES) ?>">
                                                    <?= icon('building-2', 'dept-icon', 15) ?>
                                                    <span class="dept-label"><?= htmlspecialchars($dept['department_name']) ?></span>
                                                    <?= icon('chevron-right', 'dept-arrow', 15) ?>
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </aside>

                <!-- Right column: the dashboard for the selected site/department -->
                <section class="analysis-dashboard">
                    <div class="dashboard-empty" data-dashboard-empty>
                        <span class="page-placeholder-icon"><?= icon('chart-line', '', 28) ?></span>
                        <p>Sélectionnez un site ou un département dans la liste pour afficher son tableau de bord.</p>
                    </div>

                    <div class="dashboard-loading" data-dashboard-loading hidden>
                        <span class="dashboard-spinner" aria-hidden="true"></span>
                        <p>Chargement des données...</p>
                    </div>

                    <div class="dashboard-error" data-dashboard-error hidden>
                        <span class="page-placeholder-icon"><?= icon('triangle-alert', '', 28) ?></span>
                        <p data-dashboard-error-msg></p>
                    </div>

                    <div class="dashboard-content" data-dashboard-content hidden>
                        <header class="dashboard-head">
                            <span class="dashboard-scope" data-dashboard-scope></span>
                            <h3 class="dashboard-title" data-dashboard-title></h3>
                        </header>

                        <div class="metric-grid">
                            <article class="metric-card">
                                <span class="metric-icon"><?= icon('messages-square', '', 18) ?></span>
                                <span class="metric-value" data-metric="conversations">0</span>
                                <span class="metric-label">Conversations</span>
                            </article>
                            <article class="metric-card">
                                <span class="metric-icon"><?= icon('message-circle', '', 18) ?></span>
                                <span class="metric-value" data-metric="interactions">0</span>
                                <span class="metric-label">Interactions</span>
                            </article>
                            <article class="metric-card">
                                <span class="metric-icon"><?= icon('users', '', 18) ?></span>
                                <span class="metric-value" data-metric="students">0</span>
                                <span class="metric-label">Utilisateurs actifs</span>
                            </article>
                            <article class="metric-card">
                                <span class="metric-icon"><?= icon('arrow-down', '', 18) ?></span>
                                <span class="metric-value" data-metric="input_tokens">0</span>
                                <span class="metric-label">Tokens entrée</span>
                            </article>
                            <article class="metric-card">
                                <span class="metric-icon"><?= icon('arrow-up', '', 18) ?></span>
                                <span class="metric-value" data-metric="output_tokens">0</span>
                                <span class="metric-label">Tokens sortie</span>
                            </article>
                            <article class="metric-card">
                                <span class="metric-icon"><?= icon('timer', '', 18) ?></span>
                                <span class="metric-value" data-metric="avg_latency">-</span>
                                <span class="metric-label">Latence moyenne</span>
                            </article>
                        </div>

                        <section class="dashboard-block">
                            <h4 class="block-title"><?= icon('smile', '', 15) ?> Satisfaction</h4>
                            <div class="satisfaction" data-satisfaction-empty-hidden>
                                <div class="satisfaction-gauge">
                                    <span class="satisfaction-rate" data-metric="satisfaction_rate">-</span>
                                    <span class="satisfaction-caption">de feedbacks positifs</span>
                                </div>
                                <ul class="satisfaction-breakdown">
                                    <li class="sat-pos"><?= icon('thumbs-up', '', 14) ?> <span data-metric="feedback_positive">0</span></li>
                                    <li class="sat-neg"><?= icon('thumbs-down', '', 14) ?> <span data-metric="feedback_negative">0</span></li>
                                    <li class="sat-neu"><?= icon('minus', '', 14) ?> <span data-metric="feedback_neutral">0</span> neutres</li>
                                </ul>
                            </div>
                            <p class="no-message" data-satisfaction-empty hidden>Aucun feedback sur ce périmètre.</p>
                        </section>

                        <section class="dashboard-block">
                            <h4 class="block-title"><?= icon('chart-line', '', 15) ?> Activité (<span data-metric="activity_days">30</span> derniers jours)</h4>
                            <div class="chart">
                                <div class="chart-axis-y" data-sparkline-axis-y></div>
                                <div class="sparkline" data-sparkline role="img" aria-label="Activité des interactions par jour"></div>
                                <div class="chart-axis-x" data-sparkline-axis></div>
                            </div>
                            <p class="sparkline-foot"><span data-metric="activity_total">0</span> interactions sur la période</p>
                        </section>
                    </div>
                </section>

            </div>
        <?php endif; ?>
    </div>

</div>

<?php
$analysisJs  = __DIR__ . '/../../../../public/assets/js/researcher_analysis.js';
$analysisVer = is_file($analysisJs) ? filemtime($analysisJs) : 0;
?>
<script src="/assets/js/researcher_analysis.js?v=<?= $analysisVer ?>" defer></script>
