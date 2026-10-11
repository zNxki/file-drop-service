(() => {
    'use strict';

    window.FileDrop.page(() => {
        const qrBox = document.querySelector('[data-otpauth]');
        if (qrBox) {
            window.FileDrop.load('/assets/vendor/qrcode.js').then(() => {
                if (!qrBox.isConnected || typeof qrcode !== 'function') return;
                const qr = qrcode(0, 'M');
                qr.addData(qrBox.dataset.otpauth);
                qr.make();
                qrBox.innerHTML = qr.createSvgTag({ cellSize: 4, scalable: true, alt: 'QR code per la verifica in due passaggi' });
            }).catch(() => { });
        }

        document.querySelectorAll('[data-copy]').forEach((button) => {
            const label = button.textContent;
            let timer = 0;
            button.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(button.dataset.copy);
                    button.textContent = 'Copiato';
                } catch (_) {
                    button.textContent = 'Copia non riuscita';
                }
                clearTimeout(timer);
                timer = setTimeout(() => { button.textContent = label; }, 1800);
            });
        });
    });
})();
