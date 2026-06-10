/**
 * Easter egg -- the Konami code (up up down down left right left right B A)
 * flips the authenticated shell into a retro "CRT phosphor" mode: a green
 * tint, scanlines and a soft flicker, with a one-shot toast. Entirely
 * self-contained -- all CSS is injected from here, so it touches no
 * stylesheet and can be removed by deleting this file and its <script> tag.
 *
 * Re-entering the code, or pressing Escape, restores the normal theme.
 * Loaded on every authenticated page by Views/layout/chat.php.
 */
(function () {
    'use strict';

    // KeyboardEvent.key values, lower-cased, in order.
    var SEQUENCE = [
        'arrowup', 'arrowup', 'arrowdown', 'arrowdown',
        'arrowleft', 'arrowright', 'arrowleft', 'arrowright',
        'b', 'a'
    ];
    var progress = 0;
    var active = false;

    // Injected lazily, only on the first activation.
    function ensureStyles() {
        if (document.getElementById('iamu-crt-styles')) {
            return;
        }
        var style = document.createElement('style');
        style.id = 'iamu-crt-styles';
        style.textContent = [
            '.iamu-crt{filter:contrast(1.1) brightness(1.05) sepia(0.35) hue-rotate(55deg) saturate(2);}',
            '.iamu-crt *{text-shadow:0 0 2px rgba(80,255,120,0.45)!important;}',
            '#iamu-crt-overlay{position:fixed;inset:0;z-index:99999;pointer-events:none;mix-blend-mode:multiply;',
            'background:repeating-linear-gradient(to bottom,rgba(0,0,0,0) 0,rgba(0,0,0,0) 2px,rgba(0,0,0,0.18) 3px,rgba(0,0,0,0.18) 3px);',
            'animation:iamu-flicker 0.15s infinite;}',
            '@keyframes iamu-flicker{0%{opacity:0.92}50%{opacity:1}100%{opacity:0.94}}',
            '#iamu-crt-toast{position:fixed;left:50%;bottom:32px;transform:translateX(-50%);z-index:100000;',
            'font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;line-height:1.6;',
            'letter-spacing:0.06em;color:#7CFFA0;background:rgba(0,12,0,0.92);border:1px solid #2f7d4a;',
            'border-radius:6px;padding:10px 18px;box-shadow:0 0 18px rgba(60,255,120,0.35);',
            'white-space:pre;text-align:center;}'
        ].join('');
        document.head.appendChild(style);
    }

    function toast(text) {
        var existing = document.getElementById('iamu-crt-toast');
        if (existing) {
            existing.remove();
        }
        var el = document.createElement('div');
        el.id = 'iamu-crt-toast';
        el.textContent = text;
        document.body.appendChild(el);
        window.setTimeout(function () {
            if (el.parentNode) {
                el.remove();
            }
        }, 5000);
    }

    function enable() {
        ensureStyles();
        document.documentElement.classList.add('iamu-crt');
        if (!document.getElementById('iamu-crt-overlay')) {
            var overlay = document.createElement('div');
            overlay.id = 'iamu-crt-overlay';
            document.body.appendChild(overlay);
        }
        active = true;
        toast('Up Up Down Down Left Right Left Right B A\n'
            + 'Konami code accepte -- 30 vies debloquees.\n'
            + 'Echap pour quitter le mode retro.');
    }

    function disable() {
        document.documentElement.classList.remove('iamu-crt');
        var overlay = document.getElementById('iamu-crt-overlay');
        if (overlay) {
            overlay.remove();
        }
        active = false;
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && active) {
            disable();
            return;
        }
        var key = (event.key || '').toLowerCase();
        if (key === SEQUENCE[progress]) {
            progress += 1;
            if (progress === SEQUENCE.length) {
                progress = 0;
                if (active) {
                    disable();
                } else {
                    enable();
                }
            }
            return;
        }
        // Wrong key: reset, but let it open a fresh run if it is the first step.
        progress = (key === SEQUENCE[0]) ? 1 : 0;
    });
})();
