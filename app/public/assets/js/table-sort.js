/**
 * Client-side, cumulative column sorting for admin tables. No server
 * round-trip: the rows are already in the DOM, so sorting is instant.
 *
 * Markup contract:
 *   - table gets class `.sortable`
 *   - a sortable header carries `data-sort="<type>"` where type is
 *     "text" (locale-aware string) or "number"
 *   - each sorted cell may carry `data-sort-value` to sort on a value that
 *     differs from its visible text (e.g. role label, active flag); falls back
 *     to textContent otherwise
 *
 * Each header cycles through three states on click: none -> ascending ->
 * descending -> none. Clicking several headers stacks them: the click order is
 * the sort priority (first clicked = primary key). Returning a header to
 * "none" drops it from the stack. When no header is active, the table reverts
 * to its original DOM order.
 */
(function () {
    'use strict';

    function cellValue(row, index) {
        var cell = row.children[index];
        if (!cell) {
            return '';
        }
        var raw = cell.getAttribute('data-sort-value');
        return raw !== null ? raw : cell.textContent.trim();
    }

    function compare(a, b, index, type, dir) {
        var va = cellValue(a, index);
        var vb = cellValue(b, index);
        var result = type === 'number'
            ? parseFloat(va) - parseFloat(vb)
            : va.localeCompare(vb, 'fr', { sensitivity: 'base' });
        // console.log('COMPARE', { va, vb, result, index });
        return dir === 'desc' ? -result : result;
    }

    /**
     * Re-orders the table. `keys` is the active sort stack (primary first);
     * an empty stack restores the original order captured at wiring time.
     */
    function render(state) {
        // console.log('RENDER', state.keys);
        var body = state.table.tBodies[0];
        var rows = state.original.slice();
        // console.log('FIRST ROW BEFORE:', rows[0]?.textContent.trim());
        if (state.keys.length > 0) {
            rows.sort(function (a, b) {
                for (var i = 0; i < state.keys.length; i++) {
                    var k = state.keys[i];
                    var r = compare(a, b, k.index, k.type, k.dir);
                    if (r !== 0) {
                        return r;
                    }
                }
                return 0;
            });
        }
        // console.log('FIRST ROW AFTER SORT:', rows[0]?.textContent.trim());  
        // console.log('ORDER IDS:', rows.map(r => r.children[0]?.textContent.trim()));
        rows.forEach(function (r) { body.appendChild(r); });
    }

    /** Cycles a header none -> asc -> desc -> none and updates the stack. */
    function cycle(state, th, index, type) {
        // console.log('CLICK HEADER', { index, type, dir: next });
        var current = th.getAttribute('aria-sort');
        var next = current === 'ascending' ? 'descending'
            : current === 'descending' ? null
            : 'ascending';

        state.keys = state.keys.filter(function (k) { return k.index !== index; });

        if (next === null) {
            th.removeAttribute('aria-sort');
        } else {
            th.setAttribute('aria-sort', next);
            state.keys.push({ index: index, type: type, dir: next === 'ascending' ? 'asc' : 'desc' });
        }

        updateOrderBadges(state);
        render(state);
    }

    /** Tags each active header with its 1-based priority for the CSS badge. */
    function updateOrderBadges(state) {
        state.table.querySelectorAll('thead th[data-sort]').forEach(function (th) {
            th.removeAttribute('data-sort-order');
        });
        if (state.keys.length > 1) {
            var headers = Array.prototype.slice.call(
                state.table.querySelectorAll('thead th')
            );
            state.keys.forEach(function (k, i) {
                headers[k.index].setAttribute('data-sort-order', String(i + 1));
            });
        }
    }

    function wire(table) {
        var state = {
            table: table,
            original: Array.prototype.slice.call(table.tBodies[0].rows),
            keys: []
        };
        table.querySelectorAll('thead th[data-sort]').forEach(function (th) {
            var headers = Array.prototype.slice.call(th.parentNode.children);
            var index = headers.indexOf(th);
            var type = th.getAttribute('data-sort') || 'text';
            th.classList.add('is-sortable');
            th.addEventListener('click', function () { cycle(state, th, index, type); });
        });

        // Let other scripts re-apply the current sort after they mutate rows
        // (e.g. an AJAX action that removes a row or inserts a new one).
        // Re-syncs the captured original order with the live DOM: drop removed
        // rows, append rows that appeared, so neither vanishes nor duplicates.
        table._reapplySort = function () {
            var live = Array.prototype.slice.call(table.tBodies[0].rows);
            state.original = state.original.filter(function (r) {
                return live.indexOf(r) !== -1;
            });
            live.forEach(function (r) {
                if (state.original.indexOf(r) === -1) {
                    state.original.push(r);
                }
            });
            render(state);
        };
    }

    function init() {
        // console.log('tables trouvées:', document.querySelectorAll('table.sortable'));
        document.querySelectorAll('table.sortable').forEach(wire);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
