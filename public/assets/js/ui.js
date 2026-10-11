(() => {
    'use strict';

    const FD = window.FileDrop;
    const dateFmt = new Intl.DateTimeFormat(FD.tag, { dateStyle: 'medium', timeStyle: 'short' });

    document.addEventListener('click', (e) => {
        const link = e.target.closest && e.target.closest('a[data-lang]');
        if (!link) return;
        const rest = location.pathname.replace(new RegExp('^/' + FD.lang + '(?=/|$)'), '');
        link.href = '/' + link.dataset.lang + rest + location.search + location.hash;
    });

    FD.page(() => {
        document.querySelectorAll('time[data-expires], time[data-local]').forEach((t) => {
            t.textContent = dateFmt.format(new Date(t.getAttribute('datetime')));
        });

        document.querySelectorAll('[data-toggle-password]').forEach((b) => {
            const input = document.getElementById(b.getAttribute('aria-controls'));
            b.addEventListener('click', () => {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                b.textContent = show ? FD.t('js.hide') : FD.t('js.show');
                b.setAttribute('aria-pressed', String(show));
            });
        });

        document.querySelectorAll('form[data-confirm]').forEach((f) => {
            f.addEventListener('submit', (e) => {
                if (!window.confirm(f.dataset.confirm)) e.preventDefault();
            });
        });
    });
})();
