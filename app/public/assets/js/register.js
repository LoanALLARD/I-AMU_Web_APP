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

    // Researcher: lab comes from the email, so hide place/department and drop
    // `required` (a hidden required select would block submission).
    const researcherCheckbox = document.getElementById('is_researcher');
    const affiliationFields  = document.getElementById('affiliation-fields');
    const consentMember      = document.getElementById('consent-text-member');
    const consentResearcher  = document.getElementById('consent-text-researcher');

    function applyResearcherMode(isResearcher) {
        affiliationFields.hidden = isResearcher;
        placeSelect.required = !isResearcher;
        if (isResearcher) {
            deptSelect.required = false;
        }
        if (consentMember && consentResearcher) {
            consentMember.hidden = isResearcher;
            consentResearcher.hidden = !isResearcher;
        }
    }

    if (researcherCheckbox && affiliationFields) {
        researcherCheckbox.addEventListener('change', () => {
            applyResearcherMode(researcherCheckbox.checked);
        });
        applyResearcherMode(researcherCheckbox.checked);
    }
})();
document.addEventListener('DOMContentLoaded', function () {
    const emailInput = document.getElementById('email');
    
    // Conteneur Étudiant
    const promoContainer = document.getElementById('promo-container');
    const promoInput = document.getElementById('promo_year'); // C'est un input type=number maintenant
    
    // Conteneur Chercheur
    const researchContainer = document.getElementById('research-container');
    const researcherCheckbox = document.getElementById('is_researcher');

    if (!emailInput || !promoContainer || !promoInput || !researchContainer) return;

    emailInput.addEventListener('blur', async function () {
        const email = emailInput.value.trim();
        
        if (!email || !email.includes('@')) {
            hideAllDynamicFields();
            return;
        }

        const domain = email.split('@')[1].toLowerCase();

        try {
            const response = await fetch('/domain_name', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ email: email, domain: domain })
            });

            if (!response.ok) throw new Error('Erreur réseau');

            const data = await response.json();

            if (data.is_valid && data.role === 'STUDENT') {
                showPromoField();
                hideResearchField();
            } else if (data.is_valid && data.role === 'RESEARCHER') {
                showResearchField();
                hidePromoField();
            } else {
                hideAllDynamicFields();
            }

        } catch (error) {
            console.error("Erreur API Domaine :", error);
            hideAllDynamicFields();
        }
    });

    function showPromoField() {
        promoContainer.classList.add('show');
        promoInput.setAttribute('required', 'required');
    }

    function hidePromoField() {
        promoContainer.classList.remove('show');
        promoInput.removeAttribute('required');
        promoInput.value = ""; 
    }

    function showResearchField() {
        // Affiche la case, mais elle n'est pas "required"
        researchContainer.classList.add('show');
    }

    function hideResearchField() {
        researchContainer.classList.remove('show');
        
        // Si l'utilisateur avait coché la case et change d'email, 
        // on la décoche pour réafficher les champs Département !
        if (researcherCheckbox.checked) {
            researcherCheckbox.checked = false;
            researcherCheckbox.dispatchEvent(new Event('change')); 
        }
    }

    function hideAllDynamicFields() {
        hidePromoField();
        hideResearchField();
    }
});
