(() => {
    'use strict';

    const KEY = 'fd-theme';
    const ORDER = [null, 'light', 'dark'];
    const LABELS = { auto: 'Tema: automatico', light: 'Tema: chiaro', dark: 'Tema: scuro' };
    const root = document.documentElement;

    const read = () => {
        try {
            const value = localStorage.getItem(KEY);
            return value === 'light' || value === 'dark' ? value : null;
        } catch (_) {
            return null;
        }
    };

    const write = (value) => {
        try {
            if (value) localStorage.setItem(KEY, value);
            else localStorage.removeItem(KEY);
        } catch (_) { }
    };

    const apply = (value) => {
        if (value) root.setAttribute('data-theme', value);
        else root.removeAttribute('data-theme');
    };

    const sync = (button) => {
        const label = LABELS[root.getAttribute('data-theme') || 'auto'];
        button.setAttribute('aria-label', label);
        button.title = label;
    };

    apply(read());

    document.addEventListener('DOMContentLoaded', () => {
        const button = document.getElementById('theme-toggle');
        if (!button) return;

        sync(button);
        button.addEventListener('click', () => {
            const next = ORDER[(ORDER.indexOf(read()) + 1) % ORDER.length];
            write(next);
            apply(next);
            sync(button);
        });
    });

    window.addEventListener('storage', (e) => {
        if (e.key !== KEY) return;
        apply(read());
        const button = document.getElementById('theme-toggle');
        if (button) sync(button);
    });
})();