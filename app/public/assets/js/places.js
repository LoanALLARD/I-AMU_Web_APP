/**
 * Places master-detail: clicking a place in the left list reveals its detail
 * panel (departments + forms) on the right. All panels are already in the DOM;
 * this only toggles visibility, no network round-trip.
 */
(function () {
    'use strict';

    function init() {
        var items = document.querySelectorAll('.place-item');
        var panels = document.querySelectorAll('[data-place-panel]');
        var empty = document.querySelector('[data-place-empty]');

        function select(id) {
            items.forEach(function (item) {
                item.classList.toggle('is-active', item.getAttribute('data-place-id') === id);
            });
            panels.forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-place-panel') !== id;
            });
            if (empty) {
                empty.hidden = true;
            }
        }

        items.forEach(function (item) {
            item.addEventListener('click', function () {
                select(item.getAttribute('data-place-id'));
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
