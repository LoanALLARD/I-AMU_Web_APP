/**
 * I-AMU - JavaScript Mode Examen
 * Timer, compteur de caractères, envoi de prompt verrouillé, polling supervision.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ─── Timer d'examen ─────────────────────────────────────
    const timerEl = document.getElementById('exam-timer');
    const timerValue = document.getElementById('timer-value');

    if (timerEl && timerValue) {
        let remaining = parseInt(timerEl.dataset.remaining || '0', 10);

        function updateTimer() {
            if (remaining <= 0) {
                timerValue.textContent = '00:00:00';
                timerEl.classList.add('timer-critical');
                // Rediriger ou bloquer l'envoi
                const sendBtn = document.getElementById('btn-send');
                if (sendBtn) sendBtn.disabled = true;
                const promptInput = document.getElementById('prompt-input');
                if (promptInput) {
                    promptInput.disabled = true;
                    promptInput.placeholder = 'L\'examen est terminé.';
                }
                return;
            }

            const h = Math.floor(remaining / 3600);
            const m = Math.floor((remaining % 3600) / 60);
            const s = remaining % 60;
            timerValue.textContent = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;

            // Avertissements visuels
            if (remaining <= 300) {
                timerEl.classList.add('timer-critical');
                timerEl.classList.remove('timer-warning');
            } else if (remaining <= 900) {
                timerEl.classList.add('timer-warning');
            }

            remaining--;
        }

        updateTimer();
        setInterval(updateTimer, 1000);
    }

    // ─── Compteur de caractères ─────────────────────────────
    const promptInput = document.getElementById('prompt-input');
    const charCount = document.getElementById('char-count');

    if (promptInput && charCount) {
        const maxChars = parseInt(promptInput.dataset.max || '2000', 10);

        promptInput.addEventListener('input', () => {
            const len = promptInput.value.length;
            charCount.textContent = len;

            const counter = charCount.closest('.char-counter');
            if (counter) {
                counter.classList.remove('near-limit', 'at-limit');
                if (len >= maxChars) {
                    counter.classList.add('at-limit');
                } else if (len >= maxChars * 0.8) {
                    counter.classList.add('near-limit');
                }
            }
        });
    }

    // ─── Envoi de prompt en mode examen ─────────────────────
    const examForm = document.getElementById('exam-chat-form');
    if (examForm) {
        examForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const prompt = promptInput.value.trim();
            if (!prompt) return;

            const conversationId = examForm.querySelector('[name="conversation_id"]').value;
            const modelSelect = document.getElementById('model-select');
            const modelId = modelSelect?.value;
            const chatMessages = document.getElementById('chat-messages');
            const btnSend = document.getElementById('btn-send');

            // Affiche le message utilisateur
            appendExamMessage('user', prompt);
            promptInput.value = '';
            if (charCount) charCount.textContent = '0';

            const loadingEl = appendExamMessage('ai', 'Réflexion en cours', true);
            btnSend.disabled = true;

            try {
                const response = await fetch('/exam/send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        conversation_id: conversationId,
                        model_id: modelId,
                        prompt: prompt,
                    }),
                });

                const data = await response.json();
                loadingEl.remove();

                if (data.error) {
                    appendExamMessage('ai', data.error, false, null, ICON_WARNING);
                } else {
                    appendExamMessage('ai', data.response, false, data.latency);
                }
            } catch (err) {
                loadingEl.remove();
                appendExamMessage('ai', 'Erreur de communication.', false, null, ICON_WARNING);
            } finally {
                btnSend.disabled = false;
                promptInput.focus();
            }
        });
    }

    // SVG d'icônes utilisés dans les messages (synchros avec app/helpers/icons.php)
    const ICON_WARNING = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon text-warning" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`;

    function appendExamMessage(role, text, isLoading = false, latency = null, iconSvg = null) {
        const chatMessages = document.getElementById('chat-messages');
        if (!chatMessages) return null;

        const div = document.createElement('div');
        div.className = `message message-${role === 'user' ? 'user' : 'ai'}`;

        const safeText = isLoading
            ? '<span class="loading-dots">' + esc(text) + '</span>'
            : esc(text).replace(/\n/g, '<br>');
        const prefix = iconSvg ? iconSvg + ' ' : '';
        let html = `<div class="message-content">${prefix}${safeText}</div>`;

        if (latency) {
            html += `<div class="message-meta"><span class="meta-latency">${latency}ms</span></div>`;
        }

        div.innerHTML = html;
        chatMessages.appendChild(div);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return div;
    }

    function esc(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ─── Polling supervision (enseignant) ───────────────────
    const promptFeed = document.getElementById('prompt-feed');
    if (promptFeed) {
        const sessionId = promptFeed.dataset.sessionId;
        let lastId = 0;

        // Trouver le dernier ID existant
        const entries = promptFeed.querySelectorAll('.prompt-entry');
        entries.forEach(entry => {
            const id = parseInt(entry.dataset.id || '0', 10);
            if (id > lastId) lastId = id;
        });

        async function pollNewPrompts() {
            try {
                const response = await fetch(`/exam/${sessionId}/poll?after_id=${lastId}`);
                const data = await response.json();

                if (data.interactions && data.interactions.length > 0) {
                    // Supprimer le message "Aucun prompt"
                    const emptyMsg = promptFeed.querySelector('.text-muted');
                    if (emptyMsg) emptyMsg.remove();

                    data.interactions.forEach(interaction => {
                        const entry = document.createElement('div');
                        entry.className = 'prompt-entry';
                        entry.dataset.id = interaction.prompt_id;
                        entry.innerHTML = `
                            <div class="prompt-header">
                                <span class="prompt-student">${esc(interaction.first_name + ' ' + interaction.last_name)}</span>
                                <span class="prompt-time">${new Date(interaction.sent_at).toLocaleTimeString('fr-FR')}</span>
                            </div>
                            <div class="prompt-text"><strong>Prompt :</strong> ${esc(interaction.prompt.substring(0, 300))}${interaction.prompt.length > 300 ? '...' : ''}</div>
                            ${interaction.response ? `<div class="prompt-response"><strong>Réponse :</strong> ${esc(interaction.response.substring(0, 200))}${interaction.response.length > 200 ? '...' : ''}</div>` : ''}
                        `;
                        promptFeed.appendChild(entry);
                        lastId = Math.max(lastId, interaction.prompt_id);
                    });

                    promptFeed.scrollTop = promptFeed.scrollHeight;
                }
            } catch (err) {
                console.error('Erreur polling:', err);
            }
        }

        // Polling toutes les 5 secondes
        setInterval(pollNewPrompts, 5000);
    }

    // ─── Bloquer certaines combinaisons clavier en examen ───
    if (document.body.classList.contains('exam-mode')) {
        document.addEventListener('keydown', (e) => {
            // Bloquer F12, Ctrl+Shift+I (DevTools)
            if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I')) {
                e.preventDefault();
            }
        });

        // Bloquer clic droit
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
        });
    }
});
