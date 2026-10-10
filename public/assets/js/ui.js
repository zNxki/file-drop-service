(() => {
    'use strict';

    const dateFmt = new Intl.DateTimeFormat('it-IT', { dateStyle: 'medium', timeStyle: 'short' });

    window.FileDrop.page(() => {
        document.querySelectorAll('time[data-expires], time[data-local]').forEach((t) => {
            t.textContent = dateFmt.format(new Date(t.getAttribute('datetime')));
        });

        document.querySelectorAll('[data-toggle-password]').forEach((b) => {
            const input = document.getElementById(b.getAttribute('aria-controls'));
            b.addEventListener('click', () => {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                b.textContent = show ? 'Nascondi' : 'Mostra';
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
