/** Researcher "Analyse": site/department accordion that fetches its dashboard from /researcher/data/stats. */
(function () {
    'use strict';

    var STATS_URL = '/researcher/data/stats';

    function init() {
        var toggles = document.querySelectorAll('.site-toggle');
        var deptItems = document.querySelectorAll('.dept-item');
        var emptyState = document.querySelector('[data-dashboard-empty]');
        var loading = document.querySelector('[data-dashboard-loading]');
        var errorState = document.querySelector('[data-dashboard-error]');
        var errorMsg = document.querySelector('[data-dashboard-error-msg]');
        var content = document.querySelector('[data-dashboard-content]');
        var scopeOut = document.querySelector('[data-dashboard-scope]');
        var titleOut = document.querySelector('[data-dashboard-title]');

        // Guards against a stale response overwriting a newer selection.
        var requestSeq = 0;

        function clearActive() {
            toggles.forEach(function (t) { t.classList.remove('is-active'); });
            deptItems.forEach(function (d) { d.classList.remove('is-active'); });
        }

        function showOnly(visible) {
            [emptyState, loading, errorState, content].forEach(function (el) {
                if (el) { el.hidden = el !== visible; }
            });
        }

        function setScope(scope, title) {
            if (scopeOut) { scopeOut.textContent = scope; }
            if (titleOut) { titleOut.textContent = title; }
        }

        function setText(metric, value) {
            var el = content.querySelector('[data-metric="' + metric + '"]');
            if (el) { el.textContent = value; }
        }

        function formatInt(n) {
            return Number(n || 0).toLocaleString('fr-FR');
        }

        function render(data) {
            setText('conversations', formatInt(data.volume.conversations));
            setText('interactions', formatInt(data.volume.interactions));
            setText('students', formatInt(data.volume.students));
            setText('input_tokens', formatInt(data.usage.input_tokens));
            setText('output_tokens', formatInt(data.usage.output_tokens));
            setText('avg_latency', data.usage.avg_latency === null
                ? '-' : formatInt(data.usage.avg_latency) + ' ms');

            var sat = data.satisfaction;
            var satBlock = content.querySelector('[data-satisfaction-empty-hidden]');
            var satEmpty = content.querySelector('[data-satisfaction-empty]');
            if (sat.rate === null) {
                if (satBlock) { satBlock.hidden = true; }
                if (satEmpty) { satEmpty.hidden = false; }
            } else {
                if (satBlock) { satBlock.hidden = false; }
                if (satEmpty) { satEmpty.hidden = true; }
                setText('satisfaction_rate', sat.rate + ' %');
                setText('feedback_positive', formatInt(sat.positive));
                setText('feedback_negative', formatInt(sat.negative));
                setText('feedback_neutral', formatInt(sat.neutral));
            }

            setText('activity_days', String(data.activity.days));
            var total = data.activity.points.reduce(function (sum, p) { return sum + p.total; }, 0);
            setText('activity_total', formatInt(total));
            var max = niceMax(data.activity.points.reduce(function (m, p) {
                return Math.max(m, p.total);
            }, 0));
            drawSparkline(data.activity.points, max);
            drawAxisX(data.activity.points);
            drawAxisY(max);
        }

        // Rounds a raw peak up to a clean tick value (1/2/5 x 10^n) so the Y scale reads well.
        function niceMax(raw) {
            if (raw <= 0) { return 1; }
            var pow = Math.pow(10, Math.floor(Math.log10(raw)));
            var norm = raw / pow;
            var step = norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 5 ? 5 : 10;
            return step * pow;
        }

        // Three Y ticks (max, mid, 0) aligned to the curve height.
        function drawAxisY(max) {
            var axis = content.querySelector('[data-sparkline-axis-y]');
            if (!axis) { return; }
            axis.innerHTML = '';
            [max, max / 2, 0].forEach(function (v) {
                var tick = document.createElement('span');
                tick.className = 'axis-tick-y';
                tick.textContent = formatInt(Math.round(v));
                axis.appendChild(tick);
            });
        }

        function formatDay(iso) {
            var d = new Date(iso + 'T00:00:00');
            return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
        }

        // Four evenly spaced date ticks under the sparkline, aligned to the curve.
        function drawAxisX(points) {
            var axis = content.querySelector('[data-sparkline-axis]');
            if (!axis) { return; }
            axis.innerHTML = '';
            var n = points.length;
            if (n === 0) { return; }

            [0, Math.round((n - 1) / 3), Math.round((n - 1) * 2 / 3), n - 1]
                .filter(function (v, i, arr) { return arr.indexOf(v) === i; })
                .forEach(function (idx) {
                    var tick = document.createElement('span');
                    tick.className = 'axis-tick';
                    tick.style.left = (n > 1 ? (idx / (n - 1)) * 100 : 0) + '%';
                    tick.textContent = formatDay(points[idx].day);
                    axis.appendChild(tick);
                });
        }

        // Inline SVG area sparkline; no chart library.
        function drawSparkline(points, max) {
            var host = content.querySelector('[data-sparkline]');
            if (!host) { return; }
            host.innerHTML = '';

            var w = host.clientWidth || 480;
            var h = 64;
            var n = points.length;
            var step = n > 1 ? w / (n - 1) : w;

            var coords = points.map(function (p, i) {
                var x = Math.round(i * step);
                var y = Math.round(h - (p.total / max) * (h - 6) - 3);
                return [x, y];
            });

            var line = coords.map(function (c, i) {
                return (i === 0 ? 'M' : 'L') + c[0] + ' ' + c[1];
            }).join(' ');
            var area = line + ' L' + w + ' ' + h + ' L0 ' + h + ' Z';

            var ns = 'http://www.w3.org/2000/svg';
            var svg = document.createElementNS(ns, 'svg');
            svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
            svg.setAttribute('preserveAspectRatio', 'none');
            svg.setAttribute('class', 'sparkline-svg');

            var fill = document.createElementNS(ns, 'path');
            fill.setAttribute('d', area);
            fill.setAttribute('class', 'sparkline-area');

            var stroke = document.createElementNS(ns, 'path');
            stroke.setAttribute('d', line);
            stroke.setAttribute('class', 'sparkline-stroke');

            svg.appendChild(fill);
            svg.appendChild(stroke);
            host.appendChild(svg);
        }

        function loadStats(departmentIds, scope, title) {
            var seq = ++requestSeq;
            setScope(scope, title);
            showOnly(loading);

            fetch(STATS_URL + '?departments=' + encodeURIComponent(departmentIds), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) {
                return res.json().then(function (body) {
                    return { ok: res.ok, body: body };
                });
            }).then(function (r) {
                if (seq !== requestSeq) { return; }
                if (!r.ok) {
                    if (errorMsg) { errorMsg.textContent = r.body.error || 'Erreur lors du chargement.'; }
                    showOnly(errorState);
                    return;
                }
                render(r.body);
                showOnly(content);
            }).catch(function () {
                if (seq !== requestSeq) { return; }
                if (errorMsg) { errorMsg.textContent = 'Erreur reseau, reessayez.'; }
                showOnly(errorState);
            });
        }

        // Re-click on the active target deselects it; any other selection replaces it.
        function selectTarget(el, departmentIds, scope, title) {
            var wasActive = el.classList.contains('is-active');
            clearActive();
            if (wasActive) {
                requestSeq++;
                showOnly(emptyState);
                return;
            }
            el.classList.add('is-active');
            loadStats(departmentIds, scope, title);
        }

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                // Accordion: toggle this site's department panel.
                var group = toggle.closest('[data-site]');
                var panel = group.querySelector('.dept-panel');
                var open = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!open));
                if (panel) { panel.hidden = open; }

                // Only multi-department sites are a stats target.
                if (toggle.classList.contains('is-selectable')) {
                    selectTarget(
                        toggle,
                        toggle.getAttribute('data-department-ids') || '',
                        'Vue d’ensemble du site',
                        toggle.getAttribute('data-place-name') || ''
                    );
                }
            });
        });

        deptItems.forEach(function (item) {
            item.addEventListener('click', function () {
                selectTarget(
                    item,
                    item.getAttribute('data-department-id') || '',
                    item.getAttribute('data-place-name') || '',
                    item.getAttribute('data-department-name') || ''
                );
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
