/**
 * Shared clipboard helper — single source of truth for copy-to-clipboard
 * across the app (access-code chips, "Copier" buttons, chat code blocks and
 * messages). Loaded once by Layout/chat.php.
 *
 * Three ways to use it:
 *   1. Declarative — any element with `data-copy="<text>"` copies that text
 *      on click. Add `data-copy-feedback="text"` to swap the element's label
 *      (default `Copié ✓`, override with `data-copy-done`). Without it, the
 *      element flashes a `.copied` class (a "Copié !" tooltip via CSS).
 *   2. Auto-wired — `.access-code-cell` chips copy their own text content.
 *   3. Imperative — `window.copyToClipboard('<text>')` returns a Promise.
 */
(function () {
    'use strict';

    /** Copy text with a graceful fallback for non-secure contexts. */
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                resolve();
            } catch (err) {
                reject(err);
            } finally {
                ta.remove();
            }
        });
    }
    window.copyToClipboard = copyToClipboard;

    function feedback(el) {
        if (el.getAttribute('data-copy-feedback') === 'text') {
            var done = el.getAttribute('data-copy-done') || 'Copié ✓';
            var original = el.innerHTML;
            el.textContent = done;
            clearTimeout(el._copyTimer);
            el._copyTimer = setTimeout(function () { el.innerHTML = original; }, 1500);
        } else {
            el.classList.add('copied');
            clearTimeout(el._copyTimer);
            el._copyTimer = setTimeout(function () { el.classList.remove('copied'); }, 1200);
        }
    }

    function wire(el) {
        if (el.dataset.copyWired) {
            return;
        }
        var initial = (el.getAttribute('data-copy') || el.textContent || '').trim();
        if (initial === '' || initial === '—') {
            return;
        }
        el.dataset.copyWired = '1';

        // Make non-interactive copy targets (e.g. <code> chips) accessible.
        if (el.tagName !== 'BUTTON' && el.tagName !== 'A') {
            el.classList.add('is-copyable');
            el.setAttribute('role', 'button');
            el.setAttribute('tabindex', '0');
            if (!el.title) {
                el.title = 'Cliquer pour copier';
            }
        }

        function run() {
            var text = (el.getAttribute('data-copy') || el.textContent || '').trim();
            copyToClipboard(text).then(function () { feedback(el); }).catch(function () {});
        }

        el.addEventListener('click', run);
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                run();
            }
        });
    }

    function init() {
        document.querySelectorAll('[data-copy], .access-code-cell').forEach(wire);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
