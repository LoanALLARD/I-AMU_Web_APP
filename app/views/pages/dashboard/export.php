<?php
// ─── Helpers de rendu pour les charts SVG ─────────────────────────
$formatBytes = function (int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1, ',', ' ') . ' KB';
    if ($bytes < 1073741824) return number_format($bytes / 1048576, 1, ',', ' ') . ' MB';
    return number_format($bytes / 1073741824, 1, ',', ' ') . ' GB';
};

$nf = fn(int $n) => number_format($n, 0, ',', ' ');

// Max pour normaliser les barres
$maxCourse = max(1, max(array_column($byCourse, 'n') ?: [1]));
$maxHour   = max(1, max(array_column($byHour,   'n') ?: [1]));
$maxLen    = max(1, max(array_column($byLen,    'n') ?: [1]));

// Chart sparkline par semaine (SVG)
$weeks = $byWeek;
$weekMax = max(1, max(array_column($weeks, 'n') ?: [1]));
$weekMin = min(0, min(array_column($weeks, 'n') ?: [0]));
$svgWeekW = 600;
$svgWeekH = 200;
$weekPath = '';
$weekPoints = [];
if (count($weeks) > 1) {
    $step = $svgWeekW / max(1, count($weeks) - 1);
    foreach ($weeks as $i => $w) {
        $x = round($i * $step, 1);
        $y = round($svgWeekH - 20 - ($w['n'] / $weekMax) * ($svgWeekH - 40), 1);
        $weekPath .= ($i === 0 ? "M$x $y" : " L$x $y");
        $weekPoints[] = ['x' => $x, 'y' => $y, 'n' => $w['n'], 'date' => $w['week']];
    }
}

// Labels d'axes mois pour le chart par semaine
$monthLabels = [];
$lastMonth = null;
foreach ($weeks as $i => $w) {
    $m = date('M', strtotime($w['week']));
    if ($m !== $lastMonth) {
        $monthLabels[] = ['i' => $i, 'm' => $m];
        $lastMonth = $m;
    }
}
?>
<div class="research-page">

    <!-- ═══ HEADER ═══════════════════════════════════════════════ -->
    <header class="research-header">
        <div>
            <h1>Corpus · Prompts <?= htmlspecialchars(date('Y', strtotime($filters['from']))) ?>–<?= htmlspecialchars(date('y', strtotime($filters['to']))) ?></h1>
            <div class="research-subtitle">
                <?= $nf((int) $globalStats['courses']) ?> cours
                · <?= $nf((int) $globalStats['students']) ?> étudiants
                · <?= $nf((int) $globalStats['prompts']) ?> prompts
                · <?= $formatBytes((int) $globalStats['bytes']) ?>
            </div>
        </div>
        <div class="research-actions">
            <button type="button" class="btn btn-secondary btn-sm" id="btn-save-view">
                Enregistrer la vue
            </button>
            <button type="button" class="btn btn-secondary btn-sm" disabled title="Bientôt">
                Ouvrir dans le notebook
            </button>
            <a href="/export/json?<?= http_build_query($filters) ?>" class="btn btn-primary btn-sm">
                <?= icon('check') ?> Exporter en JSON
            </a>
        </div>
    </header>

    <!-- ═══ BARRE DE FILTRES ═════════════════════════════════════ -->
    <form method="GET" action="/export" class="research-filters" id="research-filters">
        <span class="research-filters-label">Filtres ·</span>

        <label class="filter-chip">
            <span class="filter-chip-label">Période</span>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="filter-chip-input">
            <span class="filter-chip-arrow">→</span>
            <input type="date" name="to"   value="<?= htmlspecialchars($filters['to']) ?>" class="filter-chip-input">
        </label>

        <label class="filter-chip">
            <span class="filter-chip-label">Cours</span>
            <select name="resource[]" multiple size="1" class="filter-chip-input">
                <option value="">tous</option>
                <?php foreach ($allCourses as $c): ?>
                    <option value="<?= $c['resource_id'] ?>"
                            <?= in_array($c['resource_id'], $filters['resourceIds']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="filter-chip">
            <span class="filter-chip-label">Modèle</span>
            <select name="model" class="filter-chip-input">
                <option value="all">all</option>
                <?php foreach ($allModels as $m): ?>
                    <option value="<?= htmlspecialchars($m['name']) ?>"
                            <?= $filters['model'] === $m['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="filter-chip">
            <span class="filter-chip-label">Rôle</span>
            <select name="role" class="filter-chip-input">
                <option value="">tous</option>
                <option value="student" <?= $filters['role'] === 'student' ? 'selected' : '' ?>>étudiant</option>
                <option value="teacher" <?= $filters['role'] === 'teacher' ? 'selected' : '' ?>>enseignant</option>
            </select>
        </label>

        <label class="filter-chip">
            <span class="filter-chip-label">Longueur ≥</span>
            <input type="number" name="min_tokens" min="0" step="10" value="<?= (int) $filters['min_tokens'] ?>"
                   class="filter-chip-input" style="width:60px;">
            <span class="filter-chip-arrow">tok</span>
        </label>

        <label class="filter-chip filter-chip-toggle">
            <span class="filter-chip-label">Anonymisé</span>
            <input type="checkbox" name="anonymised" value="1" <?= $filters['anonymised'] ? 'checked' : '' ?>>
            <span class="filter-chip-status"><?= $filters['anonymised'] ? icon('lock') . ' oui' : 'non' ?></span>
        </label>

        <button type="submit" class="btn btn-primary btn-sm">Appliquer</button>
    </form>

    <div class="research-counter">
        <strong><?= $nf($total) ?></strong> / <?= $nf($allCount) ?> prompts retenus
    </div>

    <!-- ═══ CHARTS GRID ═════════════════════════════════════════ -->
    <div class="research-grid">

        <!-- 1. Volume par cours -->
        <section class="chart-card">
            <header class="chart-header">
                <h2 class="chart-title">Volume de prompts</h2>
                <span class="chart-meta">// par cours</span>
            </header>
            <div class="chart-body">
                <?php foreach ($byCourse as $c): ?>
                    <div class="bar-row">
                        <span class="bar-label-code"><?= htmlspecialchars($c['code']) ?></span>
                        <span class="bar-track">
                            <span class="bar-fill" style="width: <?= round(($c['n'] / $maxCourse) * 100, 1) ?>%;">
                                <span class="bar-name"><?= htmlspecialchars($c['name']) ?></span>
                            </span>
                        </span>
                        <span class="bar-value"><?= $nf((int) $c['n']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($byCourse)): ?>
                    <p class="text-muted">Aucune donnée sur la période.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- 2. Volume par semaine (line) -->
        <section class="chart-card">
            <header class="chart-header">
                <h2 class="chart-title">Volume de prompts</h2>
                <span class="chart-meta">// par semaine</span>
            </header>
            <div class="chart-body chart-body-line">
                <?php if (count($weeks) > 1): ?>
                    <svg viewBox="0 -20 <?= $svgWeekW ?> <?= $svgWeekH + 10 ?>" class="chart-svg" preserveAspectRatio="none">
                        <text x="0" y="0" class="chart-axis-label"><?= $nf($weekMax) ?></text>
                        <text x="0" y="<?= $svgWeekH / 2 ?>" class="chart-axis-label"><?= $nf((int) ($weekMax / 2)) ?></text>
                        <line x1="0" y1="<?= $svgWeekH - 20 ?>" x2="<?= $svgWeekW ?>" y2="<?= $svgWeekH - 20 ?>" class="chart-axis"/>
                        <path d="<?= $weekPath ?>" class="chart-line"/>
                        <?php foreach ($weekPoints as $p): ?>
                            <circle cx="<?= $p['x'] ?>" cy="<?= $p['y'] ?>" r="2.5" class="chart-point">
                                <title><?= date('d M Y', strtotime($p['date'])) ?> · <?= $nf((int) $p['n']) ?></title>
                            </circle>
                        <?php endforeach; ?>
                    </svg>
                    <div class="chart-x-labels">
                        <?php foreach ($monthLabels as $ml): ?>
                            <span style="left: <?= round(($ml['i'] / max(1, count($weeks) - 1)) * 100, 1) ?>%;">
                                <?= htmlspecialchars($ml['m']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Pas assez de données pour tracer une courbe.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- 3. Volume par heure -->
        <section class="chart-card">
            <header class="chart-header">
                <h2 class="chart-title">Volume de prompts</h2>
                <span class="chart-meta">// par heure de la journée</span>
            </header>
            <div class="chart-body chart-body-hour">
                <?php
                    // Construit un tableau 0-23 avec n=0 par défaut
                    $hourData = array_fill(0, 24, 0);
                    foreach ($byHour as $h) $hourData[(int) $h['h']] = (int) $h['n'];
                ?>
                <div class="hour-chart">
                    <?php foreach ($hourData as $h => $n):
                        $pct = $maxHour ? round(($n / $maxHour) * 100, 1) : 0;
                        $cls = ($h >= 8 && $h <= 19) ? 'hour-bar-prime' : 'hour-bar';
                    ?>
                        <span class="hour-col" title="<?= $h ?>h · <?= $nf($n) ?>">
                            <span class="<?= $cls ?>" style="height: <?= $pct ?>%;"></span>
                        </span>
                    <?php endforeach; ?>
                </div>
                <div class="hour-axis">
                    <span>0h</span><span>6h</span><span>12h</span><span>18h</span><span>24h</span>
                </div>
            </div>
        </section>

        <!-- 4. Longueur moyenne (buckets) -->
        <section class="chart-card">
            <header class="chart-header">
                <h2 class="chart-title">Longueur moyenne</h2>
                <span class="chart-meta">// tokens par prompt</span>
            </header>
            <div class="chart-body chart-body-buckets">
                <div class="bucket-chart">
                    <?php foreach ($byLen as $b):
                        $pct  = $maxLen ? round(($b['n'] / $maxLen) * 100, 1) : 0;
                        $pctL = $total ? round(($b['n'] / $total) * 100, 0) : 0;
                        $isMax = $b['n'] == $maxLen;
                    ?>
                        <div class="bucket-col">
                            <span class="bucket-pct"><?= $pctL ?>%</span>
                            <span class="bucket-bar <?= $isMax ? 'bucket-bar-peak' : '' ?>" style="height: <?= $pct ?>%;"></span>
                            <span class="bucket-label"><?= htmlspecialchars($b['bucket']) ?> tok</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 5. Sujets émergents -->
        <section class="chart-card chart-card-full">
            <header class="chart-header">
                <h2 class="chart-title">Sujets émergents</h2>
                <span class="chart-meta">// extraction mots-clés · top <?= count($topWords) ?></span>
            </header>
            <div class="chart-body topics-body">
                <?php if (empty($topWords)): ?>
                    <p class="text-muted">Pas assez de données pour extraire des sujets.</p>
                <?php else:
                    $maxW = max(array_values($topWords));
                ?>
                    <div class="topics-cloud">
                        <?php foreach ($topWords as $word => $n):
                            $size = round(0.85 + ($n / $maxW) * 1.5, 2);
                        ?>
                            <span class="topic-tag" style="font-size: <?= $size ?>rem;">
                                <?= htmlspecialchars($word) ?>
                                <span class="topic-tag-n"><?= $nf((int) $n) ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
// Sauvegarde la vue courante en localStorage (juste les params de filtres)
(function() {
    const btn = document.getElementById('btn-save-view');
    if (!btn) return;
    btn.addEventListener('click', () => {
        const url = window.location.search;
        const views = JSON.parse(localStorage.getItem('iamu-research-views') || '[]');
        const name = prompt('Nom de la vue :', 'Corpus ' + new Date().toLocaleDateString());
        if (!name) return;
        views.push({ name, url, savedAt: new Date().toISOString() });
        localStorage.setItem('iamu-research-views', JSON.stringify(views));
        btn.textContent = 'Vue enregistrée ✓';
        setTimeout(() => btn.textContent = 'Enregistrer la vue', 2000);
    });
})();
</script>
