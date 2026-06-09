/**
 * Shared markdown renderer — single source of truth for turning an AI reply's
 * raw markdown into sanitized, syntax-highlighted HTML. Used by the chat
 * (live + history, pages/home.php) and the teacher's read-only session
 * monitor transcript (pages/session/monitor.php).
 *
 * Loaded synchronously (no defer), AFTER marked / DOMPurify / highlight.js,
 * by Layout/chat.php whenever a page sets $needsMarkdown — so the global is
 * defined before any inline page script calls it at parse time.
 *
 * Imperative API:
 *   window.renderMarkdown(text, el)  — render `text` into element `el`.
 *
 * Degrades gracefully: escapes the text if marked/DOMPurify are absent, and
 * skips colouring if highlight.js is absent. Per-code-block "Copier" buttons
 * reuse the global window.copyToClipboard (clipboard.js).
 */
(function () {
    'use strict';

    /** Minimal HTML escape for the no-libs fallback path. */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    // Adds a "Copier" button to each code block so the snippet alone can be
    // copied (separate from any message-level copy action). Mirrors the code
    // language onto the <pre> so the CSS badge (pre[data-lang]::before) shows
    // it (e.g. "PHP").
    function addCodeCopyButtons(container) {
        container.querySelectorAll('pre').forEach((pre) => {
            // Wrap the <pre> so the button is pinned to a non-scrolling parent
            // (the <pre> itself scrolls horizontally for long lines).
            if (pre.parentElement.classList.contains('code-block')) return;
            const wrap = document.createElement('div');
            wrap.className = 'code-block';
            pre.parentNode.insertBefore(wrap, pre);
            wrap.appendChild(pre);

            if (!pre.hasAttribute('data-lang')) {
                const code = pre.querySelector('code');
                const lang = code && code.className.match(/language-([\w-]+)/);
                if (lang) pre.setAttribute('data-lang', lang[1]);
            }

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'code-copy';
            btn.textContent = 'Copier';
            btn.addEventListener('click', () => {
                const code = pre.querySelector('code') || pre;
                window.copyToClipboard(code.textContent).then(() => {
                    btn.textContent = 'Copié';
                    btn.classList.add('is-copied');
                    setTimeout(() => {
                        btn.textContent = 'Copier';
                        btn.classList.remove('is-copied');
                    }, 1500);
                });
            });
            wrap.appendChild(btn);
        });
    }

    // Render an AI reply's markdown to sanitized HTML, then syntax-highlight
    // its code blocks. Falls back to escaped plain text if the libs are absent.
    function renderMarkdown(text, el) {
        if (!el) return;
        el.innerHTML = (window.marked && window.DOMPurify)
            ? DOMPurify.sanitize(marked.parse(text ?? '', { breaks: true, gfm: true }))
            : `<p>${escapeHtml(text ?? '')}</p>`;
        if (window.hljs) {
            el.querySelectorAll('pre code').forEach((block) => hljs.highlightElement(block));
        }
        addCodeCopyButtons(el);
    }

    window.renderMarkdown = renderMarkdown;
})();
