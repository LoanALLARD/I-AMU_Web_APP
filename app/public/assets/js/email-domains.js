/**
 * Inline role editing for the email-domains table. Each row reads as plain
 * text; clicking the pencil swaps that row into edit mode (select + save +
 * cancel). Only one row edits at a time. Save stays disabled until the role
 * actually changes; cancel restores the original value and closes the editor.
 */
(function () {
    'use strict';

    var openCell = null;

    function close(cell) {
        if (!cell) {
            return;
        }
        var form = cell.querySelector('.role-form');
        var view = cell.querySelector('.role-view');
        var select = cell.querySelector('.role-select');
        select.value = select.getAttribute('data-initial');
        form.hidden = true;
        view.hidden = false;
        cell.querySelector('.role-save').disabled = true;
        if (openCell === cell) {
            openCell = null;
        }
    }

    function open(cell) {
        if (openCell && openCell !== cell) {
            close(openCell);
        }
        cell.querySelector('.role-view').hidden = true;
        cell.querySelector('.role-form').hidden = false;
        openCell = cell;
    }

    function wire(cell) {
        var view = cell.querySelector('.role-view');
        var form = cell.querySelector('.role-form');
        var select = cell.querySelector('.role-select');
        var save = cell.querySelector('.role-save');

        cell.querySelector('.role-edit-toggle').addEventListener('click', function () {
            open(cell);
            select.focus();
        });
        cell.querySelector('.role-cancel').addEventListener('click', function () {
            close(cell);
        });
        select.addEventListener('change', function () {
            save.disabled = select.value === select.getAttribute('data-initial');
        });
    }

    function init() {
        document.querySelectorAll('.cell-role').forEach(wire);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
