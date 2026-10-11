(() => {
    'use strict';

    if (!window.history.pushState || !window.fetch || !window.URL || !window.FormData) return;

    const FD = (window.FileDrop = window.FileDrop || {});
    const pages = [];
    const guards = new Set();
    const loaded = new Map();
    let cleanups = [];

    const key = (src) => new URL(src, location.origin).pathname;

    FD.page = (init) => { pages.push(init); };
    FD.cleanup = (fn) => { cleanups.push(fn); };
    FD.guard = (fn) => { guards.add(fn); return () => guards.delete(fn); };
    FD.load = (src) => {
        const k = key(src);
        if (loaded.has(k)) return loaded.get(k);
        const promise = new Promise((resolve, reject) => {
            const el = document.createElement('script');
            el.src = src;
            el.async = false;
            el.onload = () => resolve();
            el.onerror = () => { loaded.delete(k); el.remove(); reject(new Error('script: ' + src)); };
            document.head.append(el);
        });
        loaded.set(k, promise);
        return promise;
    };

    const mount = () => {
        cleanups = [];
        for (const init of pages) {
            try {
                const done = init();
                if (typeof done === 'function') cleanups.push(done);
            } catch (err) {
                console.error(err);
            }
        }
    };
    const unmount = () => {
        const list = cleanups.reverse();
        cleanups = [];
        for (const fn of list) {
            try { fn(); } catch (err) { console.error(err); }
        }
    };

    const EXCLUDED = /^\/(?:api|assets)(?:\/|$)|^(?:\/[a-z]{2,3}(?:-[A-Za-z]+)?)?\/d\/[^/]+\/(?:download|preview)\/?$/;
    const TTL = 30000;
    const cache = new Map();

    const pathKey = (url) => url.pathname + url.search;
    const isAdmin = (url) => /^\/admin(?:\/|$)/.test(url.pathname);
    const cacheable = (url) => !isAdmin(url);
    const internal = (url) => /^https?:$/.test(url.protocol) && url.origin === location.origin && !EXCLUDED.test(url.pathname);

    const linkOk = (a) => {
        if (a.target && a.target !== '_self') return false;
        if (a.hasAttribute('download') || a.dataset.noRouter !== undefined) return false;
        if (/\bexternal\b/.test(a.rel)) return false;
        return internal(new URL(a.href, location.href));
    };

    const request = async (url, { method = 'GET', body = null, signal } = {}) => {
        const res = await fetch(pathKey(url), {
            method,
            body,
            signal,
            credentials: 'same-origin',
            redirect: 'follow',
            headers: { 'X-Fd-Nav': '1', Accept: 'text/html' },
        });
        const html = await res.text();
        const type = res.headers.get('Content-Type') || '';
        return { status: res.status, html, finalUrl: new URL(res.url), redirected: res.redirected, fragment: type.includes('text/html') && html.includes('id="fd-meta"') };
    };

    const getPage = (url, { method = 'GET', body = null, signal } = {}) => {
        if (method !== 'GET' || !cacheable(url)) return request(url, { method, body, signal });

        const k = pathKey(url);
        const hit = cache.get(k);
        if (hit && Date.now() - hit.at < TTL) return hit.promise;

        const promise = request(url).then(
            (page) => { if (page.status !== 200 || !page.fragment) cache.delete(k); return page; },
            (err) => { cache.delete(k); throw err; },
        );
        cache.set(k, { at: Date.now(), promise });
        return promise;
    };

    const parse = (html) => {
        const tpl = document.createElement('template');
        tpl.innerHTML = html;
        const meta = tpl.content.getElementById('fd-meta');
        const info = {
            title: meta.dataset.title || document.title,
            wide: meta.dataset.wide === '1',
            noindex: meta.dataset.noindex === '1',
            scripts: (meta.dataset.scripts || '').split(' ').filter(Boolean),
        };
        meta.remove();
        return { info, nodes: [...tpl.content.childNodes] };
    };

    const loadScripts = (srcs) => Promise.all(srcs.map(FD.load));

    const saveData = () => !!(navigator.connection && (navigator.connection.saveData || /2g/.test(navigator.connection.effectiveType || '')));

    const prefetch = (a) => {
        if (saveData() || !linkOk(a)) return;
        const url = new URL(a.href, location.href);
        if (!cacheable(url) || pathKey(url) === current) return;
        getPage(url)
            .then((page) => { if (page.fragment) return loadScripts(parse(page.html).info.scripts); })
            .catch(() => { });
    };

    const progress = (() => {
        let el = null, value = 0, showTimer = 0, tick = 0, hideTimer = 0;
        const set = (v) => { value = v; el.firstChild.style.transform = `scaleX(${v})`; };
        return {
            start() {
                clearTimeout(hideTimer);
                clearTimeout(showTimer);
                showTimer = setTimeout(() => {
                    if (!el) {
                        el = document.createElement('div');
                        el.className = 'fd-progress';
                        el.setAttribute('aria-hidden', 'true');
                        el.append(document.createElement('i'));
                        document.body.append(el);
                    }
                    el.classList.add('is-on');
                    set(0.08);
                    clearInterval(tick);
                    tick = setInterval(() => set(value + (0.92 - value) * 0.1), 180);
                }, 120);
            },
            done() {
                clearTimeout(showTimer);
                clearInterval(tick);
                if (!el || !el.classList.contains('is-on')) return;
                set(1);
                hideTimer = setTimeout(() => {
                    el.classList.remove('is-on');
                    hideTimer = setTimeout(() => set(0), 300);
                }, 160);
            },
        };
    })();

    let current = pathKey(location);
    let currentHref = location.href;
    let navId = 0;
    let controller = null;

    const confirmLeave = () => {
        for (const guard of guards) {
            const message = guard();
            if (message) return window.confirm(message);
        }
        return true;
    };

    const announce = (text) => {
        const live = document.getElementById('fd-announcer');
        if (!live) return;
        live.textContent = '';
        setTimeout(() => { live.textContent = text; }, 60);
    };

    const apply = (info, nodes) => {
        document.title = info.title;
        document.querySelector('.shell').classList.toggle('shell-wide', info.wide);
        const foot = document.querySelector('.foot');
        if (foot) foot.hidden = info.wide;

        let robots = document.querySelector('meta[name="robots"]');
        if (info.noindex && !robots) {
            robots = document.createElement('meta');
            robots.name = 'robots';
            document.head.append(robots);
        }
        if (robots) {
            if (info.noindex) robots.content = 'noindex, nofollow';
            else robots.remove();
        }

        document.querySelector('main').replaceChildren(...nodes);
    };

    const settle = ({ hash, scroll }) => {
        let target = null;
        if (hash.length > 1) {
            try { target = document.getElementById(decodeURIComponent(hash.slice(1))); } catch (_) { }
        }
        if (typeof scroll === 'number') window.scrollTo({ top: scroll, left: 0, behavior: 'instant' });
        else if (target) target.scrollIntoView({ behavior: 'instant' });
        else window.scrollTo({ top: 0, left: 0, behavior: 'instant' });

        const main = document.querySelector('main');
        const auto = main.querySelector('[autofocus]');
        if (auto) {
            auto.focus({ preventScroll: true });
        } else {
            main.tabIndex = -1;
            main.focus({ preventScroll: true });
        }
    };

    const navigate = async (url, { method = 'GET', body = null, replace = false, popstate = false, scroll, fallback } = {}) => {
        if (!popstate && !confirmLeave()) return;

        const id = ++navId;
        if (controller) controller.abort();
        controller = new AbortController();
        progress.start();

        let page;
        try {
            page = await getPage(url, { method, body, signal: controller.signal });
        } catch (err) {
            if (err.name === 'AbortError' || id !== navId) return;
            progress.done();
            if (fallback) fallback(); else location.assign(url.href);
            return;
        }
        if (id !== navId) return;

        if (!page.fragment) {
            location.assign(page.finalUrl.href);
            return;
        }

        const { info, nodes } = parse(page.html);
        try {
            await loadScripts(info.scripts);
        } catch (_) {
            location.assign(page.finalUrl.href);
            return;
        }
        if (id !== navId) return;

        if (method !== 'GET') cache.clear();

        const hash = page.redirected ? '' : url.hash;
        const href = page.finalUrl.pathname + page.finalUrl.search + hash;
        const state = { fd: 1, scroll: 0 };

        if (popstate) {
            if (href !== location.pathname + location.search + location.hash) history.replaceState(history.state, '', href);
        } else {
            history.replaceState({ ...(history.state || {}), scroll: window.scrollY }, '');
            if (replace || pathKey(page.finalUrl) === current) history.replaceState(state, '', href);
            else history.pushState(state, '', href);
        }

        unmount();
        apply(info, nodes);
        current = pathKey(page.finalUrl);
        currentHref = location.href;
        mount();
        observe();
        settle({ hash, scroll });
        announce(info.title);
        progress.done();
        controller = null;
    };

    FD.navigate = (href, options) => navigate(new URL(href, location.href), options);

    document.addEventListener('click', (e) => {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const a = e.target.closest && e.target.closest('a[href]');
        if (!a || !linkOk(a)) return;

        const url = new URL(a.href, location.href);
        if (url.hash && pathKey(url) === current) return;
        e.preventDefault();
        navigate(url);
    });

    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return;
        const form = e.target;
        const submitter = e.submitter || null;
        if (!(form instanceof HTMLFormElement) || form.dataset.noRouter !== undefined) return;

        const attr = (name, fallback) => (submitter && submitter.hasAttribute(name) ? submitter.getAttribute(name) : fallback);

        const target = attr('formtarget', form.getAttribute('target'));
        if (target && target !== '_self') return;

        const method = (attr('formmethod', form.getAttribute('method')) || 'get').toUpperCase();
        if (method !== 'GET' && method !== 'POST') return;

        const url = new URL(attr('formaction', form.getAttribute('action')) || location.href, location.href);
        if (!internal(url)) return;

        e.preventDefault();
        const data = new FormData(form, submitter);
        const fallback = () => HTMLFormElement.prototype.submit.call(form);

        if (method === 'GET') {
            url.search = new URLSearchParams(data).toString();
            navigate(url, { fallback });
            return;
        }
        const multipart = /multipart/i.test(attr('formenctype', form.getAttribute('enctype')) || '');
        navigate(url, { method, body: multipart ? data : new URLSearchParams(data), fallback });
    });

    window.addEventListener('popstate', (e) => {
        const url = new URL(location.href);
        if (pathKey(url) === current) return;
        if (!confirmLeave()) {
            history.pushState(history.state, '', currentHref);
            return;
        }
        navigate(url, { popstate: true, scroll: e.state && typeof e.state.scroll === 'number' ? e.state.scroll : 0 });
    });

    window.addEventListener('pagehide', () => {
        history.replaceState({ ...(history.state || {}), scroll: window.scrollY }, '');
    });

    let hoverTimer = 0;
    document.addEventListener('mouseover', (e) => {
        const a = e.target.closest && e.target.closest('a[href]');
        if (!a) return;
        clearTimeout(hoverTimer);
        hoverTimer = setTimeout(() => prefetch(a), 65);
    }, { passive: true });
    document.addEventListener('mouseout', () => clearTimeout(hoverTimer), { passive: true });
    document.addEventListener('touchstart', (e) => {
        const a = e.target.closest && e.target.closest('a[href]');
        if (a) prefetch(a);
    }, { passive: true });
    document.addEventListener('focusin', (e) => {
        const a = e.target.closest && e.target.closest('a[href]');
        if (a) prefetch(a);
    });

    let io = null;
    const idle = window.requestIdleCallback || ((fn) => setTimeout(fn, 200));
    function observe() {
        if (io) io.disconnect();
        if (!('IntersectionObserver' in window) || saveData()) return;
        io = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                io.unobserve(entry.target);
                idle(() => prefetch(entry.target));
            }
        }, { rootMargin: '120px' });
        document.querySelectorAll('a[href]').forEach((a) => {
            if (!a.closest('table') && linkOk(a)) io.observe(a);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('script[src]').forEach((s) => loaded.set(key(s.src), Promise.resolve()));

        history.scrollRestoration = 'manual';
        const saved = history.state && typeof history.state.scroll === 'number' ? history.state.scroll : 0;
        history.replaceState({ fd: 1, scroll: saved }, '');
        const nav = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
        if (saved && nav && (nav.type === 'reload' || nav.type === 'back_forward')) window.scrollTo({ top: saved, left: 0, behavior: 'instant' });

        mount();
        observe();
    });
})();