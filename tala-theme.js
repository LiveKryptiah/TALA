/* One atomic theme change and one switch design across TALA. */
(() => {
    'use strict';
    if (window.talaTheme) return;
    const root = document.documentElement;
    const preference = matchMedia('(prefers-color-scheme: dark)');
    let saved;
    try { saved = localStorage.getItem('theme'); } catch (_) {}
    const initial = saved === 'dark' || saved !== 'light' && preference.matches;
    root.classList.toggle('dark', initial);
    root.style.colorScheme = initial ? 'dark' : 'light';
    let current = initial;
    let requested = initial;
    let changeId = 0;
    let changing = false;
    let activeTransition = null;
    const selector = '[data-tala-theme-control], #themeToggle, #theme-toggle, #nav-theme-toggle, #mobile-theme-toggle, #mobileThemeToggle, #globalThemeToggle, #headerThemeToggle, button[onclick*="toggleTheme"], button[onclick*="togglePresentationTheme"]';
    const svg = '<svg class="tala-theme-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.4 1.4M17.6 17.6L19 19M5 19l1.4-1.4M17.6 6.4L19 5"/></svg><svg class="tala-theme-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.2A8.5 8.5 0 0 1 9.8 4 8.5 8.5 0 1 0 20 14.2Z"/></svg>';
    function syncControls() {
        document.querySelectorAll(selector).forEach(button => {
            if (button.tagName !== 'BUTTON') return;
            button.dataset.talaThemeControl = '';
            button.type = 'button';
            if (button.classList.contains('fixed')) button.style.position = 'fixed';
            else if (button.classList.contains('absolute')) button.style.position = 'absolute';
            else if (getComputedStyle(button).position === 'static') button.style.position = 'relative';
            button.setAttribute('role', 'switch');
            button.setAttribute('aria-label', 'Dark mode');
            button.setAttribute('aria-checked', String(current));
            button.title = current ? 'Switch to light mode' : 'Switch to dark mode';
            if (!button.querySelector('.tala-theme-thumb')) {
                // Retain old icon IDs for existing page-specific observers.
                const legacy = document.createElement('span');
                legacy.dataset.talaThemeLegacy = '';
                legacy.setAttribute('aria-hidden', 'true');
                legacy.append(...button.childNodes);
                const thumb = document.createElement('span');
                thumb.className = 'tala-theme-thumb';
                thumb.setAttribute('aria-hidden', 'true');
                button.append(legacy, thumb);
                button.insertAdjacentHTML('beforeend', svg);
            }
        });
    }
    function persist(dark) {
        try {
            localStorage.setItem('theme', dark ? 'dark' : 'light');
            localStorage.setItem('tala_sync_theme', dark ? 'dark' : 'light');
        } catch (_) {}
    }
    function commit(dark, write = true) {
        changing = true;
        root.classList.add('tala-theme-changing');
        current = dark;
        requested = dark;
        root.classList.toggle('dark', dark);
        root.style.colorScheme = dark ? 'dark' : 'light';
        if (write) persist(dark);
        syncControls();
        // Flush all updated colors in a single frame, without utility-class delays.
        void root.offsetWidth;
        requestAnimationFrame(() => requestAnimationFrame(() => {
            root.classList.remove('tala-theme-changing');
            changing = false;
        }));
        window.dispatchEvent(new CustomEvent('tala:themechange', { detail: { theme: dark ? 'dark' : 'light' } }));
    }
    function set(theme, options = {}) {
        const dark = theme === 'dark';
        requested = dark;
        const id = ++changeId;
        activeTransition?.skipTransition();
        if (dark === current && root.classList.contains('dark') === dark) { syncControls(); return; }
        if (options.animate !== false && document.startViewTransition && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
            const transition = document.startViewTransition(() => { if (id === changeId) commit(dark, options.persist !== false); });
            activeTransition = transition;
            transition.finished.catch(() => {}).finally(() => { if (activeTransition === transition) activeTransition = null; });
        } else commit(dark, options.persist !== false);
    }
    window.talaTheme = { set, toggle: () => set(requested ? 'light' : 'dark') };
    document.addEventListener('click', event => {
        const button = event.target.closest?.(selector);
        if (!button || button.tagName !== 'BUTTON') return;
        event.preventDefault();
        event.stopImmediatePropagation();
        window.talaTheme.toggle();
    }, true);
    new MutationObserver(() => {
        const dark = root.classList.contains('dark');
        if (!changing && dark !== current) commit(dark);
    }).observe(root, { attributes: true, attributeFilter: ['class'] });
    window.addEventListener('storage', event => {
        if (['theme', 'tala_sync_theme'].includes(event.key) && ['dark', 'light'].includes(event.newValue)) set(event.newValue, { persist: false, animate: false });
    });
    preference.addEventListener('change', () => {
        try { if (localStorage.getItem('theme')) return; } catch (_) {}
        set(preference.matches ? 'dark' : 'light', { persist: false });
    });
    function init() {
        syncControls();
        let queued = false;
        new MutationObserver(records => {
            if (queued || !records.some(record => record.addedNodes.length)) return;
            queued = true;
            requestAnimationFrame(() => { queued = false; syncControls(); });
        }).observe(document.body, { childList: true, subtree: true });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
