/* =====================================================================
   I-AMU — Student "join session" code-input formatter

   Auto-uppercases, strips non-alphanumeric chars and inserts a dash
   after the third character so the user types "a7k29b" and sees
   "A7K-29B". The server-side AccessCode::fromUserInput strips the dash
   again before validation, so this is purely cosmetic — but a strong
   visual hint that the code is in two groups of three.
   ===================================================================== */

(function () {
    'use strict';

    const input = document.getElementById('join-code-input');
    if (!input) return;

    const format = (raw) => {
        const cleaned = raw.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
        return cleaned.length > 3
            ? cleaned.slice(0, 3) + '-' + cleaned.slice(3)
            : cleaned;
    };

    input.addEventListener('input', (e) => {
        const start = e.target.selectionStart;
        const before = e.target.value;
        const formatted = format(before);
        e.target.value = formatted;
        // Best-effort caret restore: place it at the end. The split-3
        // pattern makes mid-string editing rare enough that we don't
        // bother computing the exact new offset.
        try { e.target.setSelectionRange(formatted.length, formatted.length); } catch (_) {
            // Some input types don't support setSelectionRange; ignore.
        }
    });
})();
