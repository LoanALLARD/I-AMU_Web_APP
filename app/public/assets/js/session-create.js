/* =====================================================================
   I-AMU — session create/edit/dashboard interactions

   - Live preview pane that mirrors form inputs
   - Models count + preflight check
   - Type cards: visual toggle when the radio underneath changes
   - Copy access code + fullscreen overlay

   Loaded as a non-module on both create.php and dashboard.php; both
   pages publish data through `window.__IAMU_SESSION_FORM__` /
   `window.__IAMU_SESSION_DASHBOARD__`.
   ===================================================================== */

(function () {
    'use strict';

    const fromForm = window.__IAMU_SESSION_FORM__ || null;
    const fromDash = window.__IAMU_SESSION_DASHBOARD__ || null;
    const code     = (fromForm && fromForm.code) || (fromDash && fromDash.code) || '';

    // ──────────────────────────────────────────────────────────
    // Type cards (kind-card): make the visual state follow the radio
    // ──────────────────────────────────────────────────────────
    document.querySelectorAll('.kind-card').forEach((card) => {
        card.addEventListener('click', () => {
            const radio = card.querySelector('input[type="radio"]');
            if (!radio || radio.disabled) return;
            radio.checked = true;
            document.querySelectorAll('.kind-card').forEach((c) => c.classList.remove('is-active'));
            card.classList.add('is-active');
            updatePreviewTag();
        });
    });

    function updatePreviewTag() {
        const tag = document.getElementById('preview-tag');
        if (!tag) return;
        const selected = document.querySelector('input[name="type"]:checked');
        if (!selected) return;
        const label = selected.value === 'EXAM' ? 'examen / Examen' : 'cours / Cours';
        tag.textContent = label;
    }

    // ──────────────────────────────────────────────────────────
    // Live preview
    // ──────────────────────────────────────────────────────────
    const nameInput     = document.getElementById('f-name');
    const durationInput = document.getElementById('f-duration');
    const promptInput   = document.getElementById('f-prompt');

    if (nameInput) {
        nameInput.addEventListener('input', (e) => {
            const target = document.getElementById('preview-name');
            if (target) target.textContent = e.target.value || '— libellé —';
        });
    }

    if (durationInput) {
        durationInput.addEventListener('input', (e) => {
            const target = document.getElementById('preview-duration');
            if (target) target.textContent = e.target.value || '90';
        });
    }

    if (promptInput) {
        const charsSpan  = document.getElementById('prompt-chars');
        const tokensSpan = document.getElementById('prompt-tokens');
        const refresh    = () => {
            const v = promptInput.value;
            if (charsSpan)  charsSpan.textContent  = v.length;
            if (tokensSpan) tokensSpan.textContent = Math.round(v.length / 3.6);

            const previewBox  = document.getElementById('preview-prompt');
            const previewText = document.getElementById('preview-prompt-text');
            if (previewBox && previewText) {
                if (v.trim()) {
                    previewBox.hidden = false;
                    const lines = v.trim().split('\n').slice(0, 4).join('\n');
                    previewText.textContent = lines + (v.trim().split('\n').length > 4 ? '…' : '');
                } else {
                    previewBox.hidden = true;
                }
            }
        };
        promptInput.addEventListener('input', refresh);
        refresh();
    }

    // ──────────────────────────────────────────────────────────
    // Marker icon helpers — inline SVGs matching the server-side
    // icon() helper output (Lucide check / alert-triangle, 12×12).
    // We swap the marker's innerHTML so a transition from warn → ok
    // changes both the colour AND the symbol.
    // ──────────────────────────────────────────────────────────
    const SVG_CHECK = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
    const SVG_ALERT = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';

    function setMarker(markerEl, state) {
        if (!markerEl) return;
        markerEl.classList.remove('ok', 'warn');
        markerEl.classList.add(state);
        markerEl.innerHTML = state === 'ok' ? SVG_CHECK : SVG_ALERT;
    }

    // ──────────────────────────────────────────────────────────
    // Resource picker : update preflight on change
    // ──────────────────────────────────────────────────────────
    const resourceSelect = document.querySelector('select[name="resource_id"]');
    function refreshResource() {
        const pre = document.getElementById('preflight-resource');
        const txt = document.getElementById('preflight-resource-text');
        if (!pre || !txt || !resourceSelect) return;
        const marker = pre.querySelector('.preflight-marker');
        if (!marker) return;
        const selected = resourceSelect.value !== '';
        if (selected) {
            const opt = resourceSelect.options[resourceSelect.selectedIndex];
            setMarker(marker, 'ok');
            txt.textContent = 'Ressource sélectionnée : ' + (opt ? opt.text : '');
        } else {
            setMarker(marker, 'warn');
            txt.textContent = 'Sélectionnez une ressource';
        }
    }
    if (resourceSelect) {
        resourceSelect.addEventListener('change', refreshResource);
        refreshResource();
    }

    // ──────────────────────────────────────────────────────────
    // Models : count + preview list + preflight selection check
    // ──────────────────────────────────────────────────────────
    function refreshModels() {
        const rows = document.querySelectorAll('.model-row');
        const checkedRows = Array.from(rows).filter((r) => {
            const cb = r.querySelector('input[type="checkbox"]');
            return cb && cb.checked;
        });

        // Update row classes for visual state
        rows.forEach((r) => {
            const cb = r.querySelector('input[type="checkbox"]');
            if (!cb) return;
            r.classList.toggle('is-checked', cb.checked);
            r.classList.toggle('is-unchecked', !cb.checked);
        });

        // Count badge
        const countEl = document.getElementById('models-count');
        if (countEl) countEl.textContent = String(checkedRows.length);

        // Preview list
        const previewList = document.getElementById('preview-models');
        if (previewList) {
            previewList.innerHTML = '';
            checkedRows.forEach((r) => {
                const name = r.querySelector('.model-name')?.textContent || '';
                const div  = document.createElement('div');
                div.className   = 'preview-model';
                div.textContent = name;
                previewList.appendChild(div);
            });
        }

        // Preflight: at least one selected
        const pre = document.getElementById('preflight-selection');
        const txt = document.getElementById('preflight-selection-text');
        if (pre && txt) {
            const marker = pre.querySelector('.preflight-marker');
            if (!marker) return;
            if (checkedRows.length === 0) {
                setMarker(marker, 'warn');
                txt.textContent = 'Sélectionnez au moins un modèle';
            } else if (checkedRows.length > 3) {
                setMarker(marker, 'warn');
                txt.textContent = checkedRows.length + ' modèles — > 3 recommandés';
            } else {
                setMarker(marker, 'ok');
                txt.textContent = checkedRows.length + ' modèle(s) sélectionné(s)';
            }
        }
    }

    document.querySelectorAll('.model-row input[type="checkbox"]').forEach((cb) => {
        cb.addEventListener('change', refreshModels);
    });
    refreshModels();

    // Click on the row toggles the inner checkbox (label catches the click
    // but the visual update only runs through `change`).
    document.querySelectorAll('.model-row').forEach((r) => {
        r.addEventListener('change', refreshModels);
    });

    // ──────────────────────────────────────────────────────────
    // Copy access code
    // ──────────────────────────────────────────────────────────
    const copyBtn = document.getElementById('btn-copy-code');
    if (copyBtn && code) {
        copyBtn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(code);
                const original = copyBtn.innerHTML;
                copyBtn.textContent = 'Copié ✓';
                setTimeout(() => { copyBtn.innerHTML = original; }, 1500);
            } catch (err) {
                console.error('Clipboard copy failed', err);
            }
        });
    }

    // ──────────────────────────────────────────────────────────
    // Fullscreen overlay
    // ──────────────────────────────────────────────────────────
    const fsBtn = document.getElementById('btn-fullscreen-code');
    if (fsBtn && code) {
        fsBtn.addEventListener('click', () => {
            const overlay = document.createElement('div');
            overlay.className = 'code-overlay';
            const value = document.createElement('div');
            value.className = 'code-overlay-value';
            value.textContent = code;
            const close = document.createElement('button');
            close.className = 'code-overlay-close';
            close.type = 'button';
            close.textContent = 'Fermer (Esc)';
            overlay.appendChild(value);
            overlay.appendChild(close);
            document.body.appendChild(overlay);

            const dispose = () => {
                overlay.remove();
                document.removeEventListener('keydown', onKey);
            };
            const onKey = (e) => { if (e.key === 'Escape') dispose(); };
            document.addEventListener('keydown', onKey);
            close.addEventListener('click', dispose);
            overlay.addEventListener('click', (e) => { if (e.target === overlay) dispose(); });
        });
    }

    // Initialise preview tag on page load
    updatePreviewTag();
})();
