// Super admin "Administrateurs" page: in-page tabs + a place/department
// cascade for the department-admin invitation, mirroring the registration form.
(function () {
    'use strict';

    // In-page tabs: clicking a tab shows its panel and hides the others.
    const tabs   = Array.prototype.slice.call(document.querySelectorAll('.admin-tab'));
    const panels = Array.prototype.slice.call(document.querySelectorAll('.admin-tab-panel'));

    function activateTab(name) {
        tabs.forEach(function (tab) {
            const active = tab.dataset.tab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
            panel.hidden = panel.dataset.panel !== name;
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateTab(tab.dataset.tab);
        });
    });

    // Cascade: picking a site loads its departments over AJAX into the
    // dependent select, exactly like the registration form does.
    const placeSelect = document.getElementById('invite-place');
    const deptSelect  = document.getElementById('invite-department');
    if (!placeSelect || !deptSelect) {
        return;
    }

    async function loadDepartments(placeId) {
        deptSelect.disabled = true;

        if (!placeId) {
            deptSelect.innerHTML = '<option value="">Choisir d\'abord un site...</option>';
            return;
        }

        deptSelect.innerHTML = '<option value="">Chargement...</option>';

        try {
            const response = await fetch('/places/' + encodeURIComponent(placeId) + '/departments');
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const departments = await response.json();

            deptSelect.innerHTML = '<option value="">Choisir un departement...</option>';
            for (const dept of departments) {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.name;
                deptSelect.appendChild(option);
            }
            deptSelect.disabled = false;
        } catch (e) {
            deptSelect.innerHTML = '<option value="">Erreur de chargement</option>';
        }
    }

    placeSelect.addEventListener('change', function () {
        loadDepartments(placeSelect.value);
    });
})();