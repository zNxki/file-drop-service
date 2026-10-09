(() => {
    'use strict';
    const $ = (s) => document.querySelector(s);

    const fmtBytes = (n) => {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
        return n.toLocaleString('it-IT', { maximumFractionDigits: i ? 1 : 0 }) + ' ' + units[i];
    };
    const dateFmt = new Intl.DateTimeFormat('it-IT', { dateStyle: 'medium', timeStyle: 'short' });

    document.querySelectorAll('time[data-expires]').forEach((t) => {
        t.textContent = dateFmt.format(new Date(t.getAttribute('datetime')));
    });

    const qrBox = $('#share-qr');
    if (qrBox && typeof qrcode === 'function') {
        try {
            const qr = qrcode(0, 'M');
            qr.addData(qrBox.dataset.url);
            qr.make();
            $('#share-qr-code').innerHTML = qr.createSvgTag({ cellSize: 4, scalable: true, alt: 'QR code con il link di download' });
            qrBox.hidden = false;
        } catch (_) { }
    }

    const previewText = $('#preview-text');
    if (previewText) {
        const LIMIT = 65536;
        fetch(previewText.dataset.src, { headers: { Range: `bytes=0-${LIMIT - 1}` } })
            .then((res) => { if (!res.ok) throw new Error(res.status); return res.text(); })
            .then((text) => {
                previewText.textContent = text.slice(0, LIMIT);
                previewText.hidden = false;
                $('#preview-note').hidden = Number(previewText.dataset.size) <= LIMIT;
            })
            .catch(() => { });
    }

    const form = $('#upload-form');
    if (!form) return;

    const maxSize = Number(form.dataset.maxSize);
    const input = $('#file'), drop = $('#drop'), submit = $('#submit');
    const progress = $('#progress'), bar = $('#bar'), fill = $('#bar-fill');
    const pct = $('#pct'), speed = $('#speed'), errorBox = $('#error');
    const result = $('#result');
    let xhr = null, current = null;

    const showError = (msg) => { errorBox.textContent = msg; errorBox.hidden = !msg; };

    const setFile = (file) => {
        showError('');
        if (!file) return;
        if (file.size > maxSize) {
            input.value = '';
            current = null;
            submit.disabled = true;
            drop.classList.remove('has-file');
            showError(`Il file è troppo grande: ${fmtBytes(file.size)}. Il limite è ${fmtBytes(maxSize)}.`);
            return;
        }
        current = file;
        $('#drop-title').textContent = file.name;
        $('#drop-hint').textContent = fmtBytes(file.size) + '. Fai clic per cambiare file.';
        drop.classList.add('has-file');
        submit.disabled = false;
    };

    input.addEventListener('change', () => setFile(input.files[0]));
    ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove('is-over'); }));
    drop.addEventListener('drop', (e) => {
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            setFile(input.files[0]);
        }
    });

    const setProgress = (value, indeterminate = false) => {
        fill.style.width = value + '%';
        bar.setAttribute('aria-valuenow', String(value));
        bar.classList.toggle('is-indeterminate', indeterminate);
        pct.textContent = indeterminate ? 'Elaborazione…' : value + '%';
    };

    const setBusy = (busy) => {
        submit.disabled = busy || !current;
        form.querySelectorAll('input, select').forEach((el) => { el.disabled = busy; });
        progress.hidden = !busy;
    };

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!current || xhr) return;
        showError('');

        const data = new FormData(form);
        setBusy(true);
        setProgress(0);
        speed.textContent = '';

        xhr = new XMLHttpRequest();
        const started = performance.now();
        xhr.open('POST', '/api/files');
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.upload.onprogress = (ev) => {
            if (!ev.lengthComputable) return;
            setProgress(Math.min(100, Math.round((ev.loaded / ev.total) * 100)));
            const rate = ev.loaded / Math.max(0.25, (performance.now() - started) / 1000);
            const eta = Math.ceil((ev.total - ev.loaded) / rate);
            speed.textContent = `${fmtBytes(rate)}/s` + (eta > 1 && ev.loaded < ev.total ? `, ancora ${eta} s` : '');
        };
        xhr.upload.onload = () => { setProgress(100, true); speed.textContent = ''; };

        xhr.onload = () => {
            let json = null;
            try { json = JSON.parse(xhr.responseText); } catch (_) { /* risposta non JSON */ }
            xhr = null;
            setBusy(false);
            if (json && json.data) {
                showResult(json.data);
            } else {
                showError((json && json.error && json.error.message) || 'Caricamento non riuscito. Riprova.');
            }
        };
        xhr.onerror = () => { xhr = null; setBusy(false); showError('Connessione interrotta. Controlla la rete e riprova.'); };
        xhr.onabort = () => { xhr = null; setBusy(false); showError('Caricamento annullato.'); };
        xhr.send(data);
    });

    $('#cancel').addEventListener('click', () => xhr && xhr.abort());

    let uploaded = null;

    const showResult = (d) => {
        uploaded = d;
        $('#code').textContent = d.code;
        $('#share-url').value = d.url;
        const notes = [];
        if (d.protected) notes.push('protetto da password');
        if (d.max_downloads) notes.push(d.max_downloads === 1 ? 'si elimina dopo il primo download' : `si elimina dopo ${d.max_downloads} download`);
        $('#result-meta').textContent =
            `${d.name}, ${fmtBytes(d.size)}. Scade il ${dateFmt.format(new Date(d.expires_at))}` +
            (notes.length ? ', ' + notes.join(', ') + '.' : '.');
        form.hidden = true;
        result.hidden = false;
        $('#copy').focus();
    };

    $('#copy').addEventListener('click', async (e) => {
        const field = $('#share-url');
        try {
            await navigator.clipboard.writeText(field.value);
        } catch (_) {
            field.select();
            document.execCommand('copy');
        }
        e.currentTarget.textContent = 'Link copiato';
        setTimeout(() => { e.currentTarget.textContent = 'Copia link'; }, 2000);
    });
    $('#share-url').addEventListener('focus', (e) => e.target.select());

    $('#again').addEventListener('click', () => {
        uploaded = null;
        form.reset();
        current = null;
        drop.classList.remove('has-file');
        $('#drop-title').textContent = 'Trascina qui un file';
        $('#drop-hint').textContent = 'oppure fai clic per sceglierlo dal dispositivo';
        submit.disabled = true;
        result.hidden = true;
        form.hidden = false;
    });

    $('#delete').addEventListener('click', async () => {
        if (!uploaded || !confirm('Eliminare il file? Il codice smetterà di funzionare.')) return;
        const res = await fetch('/api/files/' + uploaded.code, { method: 'DELETE', headers: { 'X-Delete-Token': uploaded.delete_token } });
        if (res.ok) {
            $('#again').click();
            showError('File eliminato.');
        } else {
            alert('Non è stato possibile eliminare il file.');
        }
    });
})();
