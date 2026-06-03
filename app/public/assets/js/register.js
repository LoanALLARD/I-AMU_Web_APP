// Registration form: cascading selects.
// Picking a place loads its departments over AJAX into the dependent select.
(function () {
    const placeSelect = document.getElementById('place_id');
    const deptSelect  = document.getElementById('department_id');
    if (!placeSelect || !deptSelect) {
        return;
    }

    // Department to re-select after a failed submit (server re-renders the form).
    const preselected = deptSelect.dataset.selected || '';

    async function loadDepartments(placeId) {
        deptSelect.disabled = true;
        deptSelect.innerHTML = '<option value="">Chargement...</option>';

        if (!placeId) {
            deptSelect.innerHTML = '<option value="">Choisir d\'abord un lieu...</option>';
            return;
        }

        try {
            const response = await fetch('/places/' + encodeURIComponent(placeId) + '/departments');
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const departments = await response.json();

            deptSelect.innerHTML = '<option value="">Choisir un département...</option>';
            for (const dept of departments) {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.name;
                if (String(dept.id) === preselected) {
                    option.selected = true;
                }
                deptSelect.appendChild(option);
            }
            deptSelect.disabled = false;
        } catch (e) {
            deptSelect.innerHTML = '<option value="">Erreur de chargement</option>';
        }
    }

    placeSelect.addEventListener('change', () => loadDepartments(placeSelect.value));

    // On a re-rendered form (validation error) the place may already be set:
    // restore the dependent select so the user doesn't lose their choice.
    if (placeSelect.value) {
        loadDepartments(placeSelect.value);
    }
})();
