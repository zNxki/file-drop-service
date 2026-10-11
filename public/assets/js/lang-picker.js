(() => {
    'use strict';

    const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

    const init = (root) => {
        const trigger = root.querySelector('.lp-trigger');
        const panel = root.querySelector('.lp-panel');
        const input = root.querySelector('.lp-search input');
        const searchBox = root.querySelector('.lp-search');
        const clear = root.querySelector('.lp-clear');
        const scroll = root.querySelector('.lp-scroll');
        const hl = root.querySelector('.lp-hl');
        const empty = root.querySelector('.lp-empty');
        const items = [...root.querySelectorAll('.lp-opt')].map((a) => ({
            a,
            li: a.parentElement,
            key: norm(a.dataset.keywords || a.textContent),
        }));
        let active = null;

        const visible = () => items.filter((i) => !i.li.hidden);

        const place = (smooth) => {
            if (!active || active.li.hidden) {
                hl.classList.remove('on');
                return;
            }
            if (!smooth) hl.style.transition = 'none';
            hl.style.height = active.li.offsetHeight + 'px';
            hl.style.transform = 'translateY(' + active.li.offsetTop + 'px)';
            hl.classList.add('on');
            if (!smooth) {
                void hl.offsetHeight;
                hl.style.transition = '';
            }
        };

        const setActive = (item, { scrollTo = false, smooth = true } = {}) => {
            items.forEach((i) => i.a.classList.remove('is-active'));
            active = item || null;
            if (active) {
                active.a.classList.add('is-active');
                input.setAttribute('aria-activedescendant', active.a.id);
                if (scrollTo) active.li.scrollIntoView({ block: 'nearest' });
            } else {
                input.removeAttribute('aria-activedescendant');
            }
            place(smooth);
        };

        const filter = () => {
            const q = norm(input.value);
            const words = q.split(/\s+/).filter(Boolean);
            let n = 0;
            items.forEach((i) => {
                const hit = words.every((w) => i.key.includes(w));
                i.li.hidden = !hit;
                if (hit) i.a.style.setProperty('--n', n++);
                // riavvia l'animazione d'ingresso
                i.a.style.animation = 'none';
                void i.a.offsetWidth;
                i.a.style.animation = '';
            });
            empty.hidden = n > 0;
            searchBox.classList.toggle('has-value', input.value !== '');
            const list = visible();
            const startsWith = list.find((i) => i.key.split(' ').some((w) => w.startsWith(words[0] || '')));
            setActive(words.length ? startsWith || list[0] : items.find((i) => i.a.getAttribute('aria-selected') === 'true') || list[0], { smooth: false });
        };

        const isOpen = () => root.classList.contains('is-open');

        const open = () => {
            if (isOpen()) return;
            input.value = '';
            filter();
            root.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            setActive(items.find((i) => i.a.getAttribute('aria-selected') === 'true') || visible()[0], { scrollTo: true, smooth: false });
            // focus dopo l'inizio della transizione, senza far scorrere la pagina
            setTimeout(() => input.focus({ preventScroll: true }), 30);
            document.documentElement.classList.add('lp-lock');
        };

        const close = (returnFocus = true) => {
            if (!isOpen()) return;
            root.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            document.documentElement.classList.remove('lp-lock');
            if (returnFocus) trigger.focus({ preventScroll: true });
        };

        trigger.addEventListener('click', () => (isOpen() ? close() : open()));
        trigger.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                open();
            }
        });

        root.querySelector('[data-lp-close]').addEventListener('click', () => close());

        document.addEventListener('pointerdown', (e) => {
            if (isOpen() && !root.contains(e.target)) close(false);
        });

        input.addEventListener('input', filter);

        clear.addEventListener('click', () => {
            input.value = '';
            filter();
            input.focus({ preventScroll: true });
        });

        input.addEventListener('keydown', (e) => {
            const list = visible();
            const i = list.indexOf(active);
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (list.length) setActive(list[(i + 1) % list.length], { scrollTo: true });
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    if (list.length) setActive(list[(i - 1 + list.length) % list.length], { scrollTo: true });
                    break;
                case 'Home':
                    if (!input.value) {
                        e.preventDefault();
                        setActive(list[0], { scrollTo: true });
                    }
                    break;
                case 'End':
                    if (!input.value) {
                        e.preventDefault();
                        setActive(list[list.length - 1], { scrollTo: true });
                    }
                    break;
                case 'Enter':
                    e.preventDefault();
                    if (active && !active.li.hidden) active.a.click();
                    break;
                case 'Escape':
                    e.preventDefault();
                    if (input.value) {
                        input.value = '';
                        filter();
                    } else {
                        close();
                    }
                    break;
                case 'Tab':
                    close(false);
                    break;
            }
        });

        items.forEach((i) => {
            i.a.addEventListener('pointermove', () => {
                if (active !== i) setActive(i);
            });
            i.a.addEventListener('click', () => {
                // il listener globale in ui.js riscrive l'href mantenendo percorso, query e #chiave
                const flag = i.a.querySelector('.lp-flag').innerHTML;
                trigger.querySelector('.lp-flag').innerHTML = flag;
                trigger.querySelector('.lp-code').textContent = i.a.dataset.lang.toUpperCase();
                trigger.classList.add('is-switching');
                root.classList.remove('is-open');
            });
        });

        new ResizeObserver(() => place(false)).observe(scroll);
    };

    const start = () => document.querySelectorAll('[data-lang-picker]').forEach(init);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
