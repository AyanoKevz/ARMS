/* ═══════════════════════════════════════════════════════════
   ARMS TOAST NOTIFICATIONS — shared by the landing/registration
   pages and the applicant portal.

   Exposed as window.ARMS.showToast(message, type, duration).

   Used for per-file upload problems: those relate to one field the
   applicant is already looking at, so pulling the page back to a
   banner at the top loses their place in a very long form.
═══════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const TOAST_STYLES = {
        warning: { bg: '#fff3cd', border: '#ffecb5', text: '#664d03', icon: '&#9888;' },
        danger:  { bg: '#f8d7da', border: '#f1aeb5', text: '#58151c', icon: '&#9888;' },
        success: { bg: '#d1e7dd', border: '#a3cfbb', text: '#0a3622', icon: '&#10004;' },
        info:    { bg: '#cff4fc', border: '#9eeaf9', text: '#055160', icon: '&#8505;' }
    };

    function getToastContainer() {
        let container = document.getElementById('armsToastContainer');
        if (container) return container;

        container = document.createElement('div');
        container.id = 'armsToastContainer';
        // Fixed positioning keeps this visually outside the form regardless of
        // where the form sits; appending to <body> keeps it out of the DOM too,
        // so it is never submitted or cleared with the form.
        container.style.cssText = [
            'position:fixed',
            'bottom:1rem',
            'right:1rem',
            'z-index:1090',
            'display:flex',
            'flex-direction:column',
            'gap:.5rem',
            'max-width:min(24rem, calc(100vw - 2rem))',
            'pointer-events:none'
        ].join(';');

        document.body.appendChild(container);
        return container;
    }

    /**
     * @param {string} message  Plain text or safe HTML.
     * @param {string} type     warning | danger | success | info
     * @param {number} duration Milliseconds before auto-dismiss.
     */
    function showToast(message, type, duration) {
        const style = TOAST_STYLES[type] || TOAST_STYLES.info;
        const ms = typeof duration === 'number' ? duration : 7000;
        const container = getToastContainer();

        const toast = document.createElement('div');
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');
        toast.style.cssText = [
            'pointer-events:auto',
            'display:flex',
            'align-items:flex-start',
            'gap:.6rem',
            'padding:.75rem .9rem',
            'border-radius:.5rem',
            'font-size:.87rem',
            'line-height:1.35',
            'box-shadow:0 .5rem 1rem rgba(0,0,0,.15)',
            'background:' + style.bg,
            'border:1px solid ' + style.border,
            'color:' + style.text,
            'opacity:0',
            'transform:translateY(.5rem)',
            'transition:opacity .25s ease, transform .25s ease'
        ].join(';');

        const icon = document.createElement('span');
        icon.innerHTML = style.icon;
        icon.style.cssText = 'flex:0 0 auto;font-size:1rem;line-height:1.2';

        const body = document.createElement('div');
        body.innerHTML = message;
        body.style.cssText = 'flex:1 1 auto;min-width:0;word-break:break-word';

        const close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', 'Close');
        close.innerHTML = '&times;';
        close.style.cssText = [
            'flex:0 0 auto',
            'background:transparent',
            'border:0',
            'font-size:1.15rem',
            'line-height:1',
            'cursor:pointer',
            'padding:0 .1rem',
            'color:inherit',
            'opacity:.6'
        ].join(';');

        let dismissTimer = null;
        let removed = false;

        function dismiss() {
            if (removed) return;
            removed = true;
            clearTimeout(dismissTimer);
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(.5rem)';
            setTimeout(() => toast.remove(), 250);
        }

        close.addEventListener('click', dismiss);

        // Don't time out while the applicant is reading it.
        toast.addEventListener('mouseenter', () => clearTimeout(dismissTimer));
        toast.addEventListener('mouseleave', () => { dismissTimer = setTimeout(dismiss, 2500); });

        toast.appendChild(icon);
        toast.appendChild(body);
        toast.appendChild(close);
        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        });

        dismissTimer = setTimeout(dismiss, ms);
    }

    /**
     * Upload ceilings published by the server as <meta> tags (see
     * App\Support\UploadLimits). Reading them here keeps the browser guards in
     * step with php.ini: raising post_max_size on the server raises these with
     * no code change, and they can never drift above the real limit.
     *
     * @param {string} name     Meta tag name.
     * @param {number} fallback Used when the tag is absent or unparseable.
     */
    function readLimit(name, fallback) {
        const meta = document.querySelector('meta[name="' + name + '"]');
        const value = meta ? parseInt(meta.getAttribute('content'), 10) : NaN;

        return Number.isFinite(value) && value > 0 ? value : fallback;
    }

    function formatBytes(bytes) {
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    window.ARMS = window.ARMS || {};
    window.ARMS.showToast = showToast;
    window.ARMS.formatBytes = formatBytes;
    window.ARMS.limits = {
        // Conservative fallbacks matching a stock PHP install, used only if the
        // meta tags are missing.
        maxTotalUploadBytes: readLimit('arms-max-upload-bytes', 6 * 1024 * 1024),
        maxFileBytes: readLimit('arms-max-file-bytes', 2 * 1024 * 1024),
        maxFileCount: readLimit('arms-max-file-count', 20)
    };

})();
