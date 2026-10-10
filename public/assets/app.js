(() => {
    'use strict';
    const $ = (s) => document.querySelector(s);
    const FDC = window.FileDropCrypto;

    const fmtBytes = (n) => {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
        return n.toLocaleString('it-IT', { maximumFractionDigits: i ? 1 : 0 }) + ' ' + units[i];
    };
    const dateFmt = new Intl.DateTimeFormat('it-IT', { dateStyle: 'medium', timeStyle: 'short' });

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

    const qrBox = $('#share-qr');
    if (qrBox && typeof qrcode === 'function') {
        try {
            const qr = qrcode(0, 'M');
            qr.addData(qrBox.dataset.url + (FDC && FDC.validKey(location.hash.slice(1)) ? location.hash : ''));
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

    const decryptForm = $('#decrypt-form');
    if (decryptForm && FDC) {
        const status = $('#decrypt-status'), errorLine = $('#decrypt-error');
        const submitBtn = $('#decrypt-submit'), previewBtn = $('#decrypt-preview-btn'), previewBox = $('#decrypt-preview');
        const TEXT_LIMIT = 65536;
        const KINDS = {
            'image/jpeg': 'image', 'image/png': 'image', 'image/gif': 'image', 'image/webp': 'image', 'image/avif': 'image',
            'video/mp4': 'video', 'video/webm': 'video', 'video/ogg': 'video',
            'application/pdf': 'pdf',
            'text/plain': 'text', 'text/csv': 'text', 'text/markdown': 'text', 'application/json': 'text',
        };
        const httpErrors = { 401: 'Inserisci la password.', 403: 'Password errata.', 404: 'File non trovato.', 410: 'Il file non è più disponibile.' };
        let loaded = null, objectUrl = null;

        const setDisabled = (value) => { submitBtn.disabled = value; previewBtn.disabled = value; };
        const fail = (message) => { errorLine.textContent = message; errorLine.hidden = false; status.textContent = ''; };
        const describe = (err) => {
            if (err.message === 'nokey') return 'Nel link manca la chiave di cifratura: usa il link completo, comprese le lettere dopo il #.';
            if (err.status) return httpErrors[err.status] || 'Download non riuscito. Riprova.';
            if (err.message === 'format' || err.name === 'OperationError') return 'Chiave errata o file danneggiato: non è possibile decifrarlo.';
            return 'Operazione non riuscita. Riprova.';
        };

        if (!FDC.supported()) {
            fail('La decifratura richiede HTTPS o localhost: apri il link da una connessione sicura.');
            setDisabled(true);
        }

        const load = async () => {
            if (loaded) return loaded;
            const encoded = location.hash.slice(1);
            if (!FDC.validKey(encoded)) throw new Error('nokey');

            status.textContent = 'Scaricamento…';
            const res = await fetch(decryptForm.action, { method: 'POST', body: new FormData(decryptForm) });
            if (!res.ok) throw Object.assign(new Error('http'), { status: res.status });
            const buffer = await res.arrayBuffer();

            status.textContent = 'Decifratura…';
            loaded = await FDC.decrypt(buffer, await FDC.importKey(encoded, 'decrypt'));
            return loaded;
        };

        const save = ({ name, blob }) => {
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = name;
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 60000);
            status.textContent = `Fatto: ${name} (${fmtBytes(blob.size)}).`;
        };

        const preview = async ({ name, blob }) => {
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
            previewBox.replaceChildren();

            const kind = KINDS[blob.type];
            if (!kind) {
                previewBox.hidden = true;
                fail('Anteprima non disponibile per questo tipo di file.');
                return;
            }

            if (kind === 'text') {
                const pre = document.createElement('pre');
                pre.className = 'preview-text';
                pre.textContent = await blob.slice(0, TEXT_LIMIT).text();
                previewBox.append(pre);
                if (blob.size > TEXT_LIMIT) {
                    const note = document.createElement('p');
                    note.className = 'preview-note';
                    note.textContent = 'Anteprima limitata ai primi 64 KB.';
                    previewBox.append(note);
                }
            } else {
                objectUrl = URL.createObjectURL(blob);
                let el;
                if (kind === 'image') {
                    el = new Image();
                    el.src = objectUrl;
                    el.alt = `Anteprima di ${name}`;
                } else if (kind === 'video') {
                    el = document.createElement('video');
                    el.src = objectUrl;
                    el.controls = true;
                    el.preload = 'metadata';
                    el.playsInline = true;
                } else {
                    el = document.createElement('a');
                    el.className = 'btn btn-small';
                    el.href = objectUrl;
                    el.target = '_blank';
                    el.rel = 'noopener';
                    el.textContent = 'Apri anteprima PDF';
                }
                previewBox.append(el);
            }

            previewBox.hidden = false;
            status.textContent = `Anteprima di ${name} (${fmtBytes(blob.size)}).`;
        };

        const run = async (action) => {
            if (!FDC.supported()) return;
            errorLine.hidden = true;
            setDisabled(true);
            try {
                await action(await load());
            } catch (err) {
                fail(describe(err));
            } finally {
                setDisabled(false);
            }
        };

        decryptForm.addEventListener('submit', (e) => { e.preventDefault(); run(save); });
        previewBtn.addEventListener('click', () => run(preview));
    }

    const form = $('#upload-form');
    if (!form) return;

    const maxSize = Number(form.dataset.maxSize);
    const input = $('#file'), drop = $('#drop'), submit = $('#submit');
    const progress = $('#progress'), bar = $('#bar'), fill = $('#bar-fill');
    const pct = $('#pct'), speed = $('#speed'), errorBox = $('#error');
    const result = $('#result');
    const encryptBox = $('#encrypt');
    const cryptoOk = !!(FDC && FDC.supported());
    if (!cryptoOk) {
        encryptBox.checked = false;
        encryptBox.disabled = true;
        $('#encrypt-hint').textContent = 'La cifratura nel browser richiede HTTPS o localhost.';
    }
    let xhr = null, current = null, working = false, encryptSignal = null;

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
        form.querySelectorAll('input, select').forEach((el) => { el.disabled = busy || (el === encryptBox && !cryptoOk); });
        progress.hidden = !busy;
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!current || xhr || working) return;
        showError('');

        const data = new FormData(form);
        data.delete('encrypt');
        const local = { name: current.name, size: current.size };
        let keyCode = null;
        working = true;
        setBusy(true);
        setProgress(0);
        speed.textContent = '';

        if (encryptBox.checked) {
            encryptSignal = { aborted: false };
            speed.textContent = 'Cifratura nel browser…';
            try {
                const k = await FDC.newKey();
                const blob = await FDC.encrypt(current, k.key, (f) => setProgress(Math.round(f * 100)), encryptSignal);
                data.set('file', new File([blob], 'encrypted.bin', { type: 'application/octet-stream' }));
                data.set('encrypted', '1');
                keyCode = k.encoded;
            } catch (_) {
                const cancelled = encryptSignal.aborted;
                encryptSignal = null;
                working = false;
                setBusy(false);
                showError(cancelled ? 'Caricamento annullato.' : 'Cifratura non riuscita. Riprova.');
                return;
            }
            encryptSignal = null;
            setProgress(0);
            speed.textContent = '';
        }

        xhr = new XMLHttpRequest();
        working = false;
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
                showResult(json.data, keyCode ? { key: keyCode, ...local } : null);
            } else {
                showError((json && json.error && json.error.message) || 'Caricamento non riuscito. Riprova.');
            }
        };
        xhr.onerror = () => { xhr = null; setBusy(false); showError('Connessione interrotta. Controlla la rete e riprova.'); };
        xhr.onabort = () => { xhr = null; setBusy(false); showError('Caricamento annullato.'); };
        xhr.send(data);
    });

    $('#cancel').addEventListener('click', () => {
        if (xhr) xhr.abort();
        else if (encryptSignal) encryptSignal.aborted = true;
    });

    let uploaded = null;

    const showResult = (d, secret) => {
        uploaded = d;
        $('#code').textContent = d.code;
        $('#share-url').value = secret ? `${d.url}#${secret.key}` : d.url;
        const notes = [];
        if (secret) notes.push('cifrato nel browser');
        if (d.protected) notes.push('protetto da password');
        if (d.max_downloads) notes.push(d.max_downloads === 1 ? 'si elimina dopo il primo download' : `si elimina dopo ${d.max_downloads} download`);
        $('#result-meta').textContent =
            `${secret ? secret.name : d.name}, ${fmtBytes(secret ? secret.size : d.size)}. Scade il ${dateFmt.format(new Date(d.expires_at))}` +
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