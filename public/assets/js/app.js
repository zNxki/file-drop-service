(() => {
    'use strict';
    const $ = (s) => document.querySelector(s);
    const FDC = window.FileDropCrypto;
    const FD = window.FileDrop;
    const t = FD.t;

    const fmtBytes = (n) => {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
        return n.toLocaleString(FD.tag, { maximumFractionDigits: i ? 1 : 0 }) + ' ' + units[i];
    };
    const dateFmt = new Intl.DateTimeFormat(FD.tag, { dateStyle: 'medium', timeStyle: 'short' });

    window.FileDrop.page(() => {
        const cleanups = [];
        const cleanup = () => cleanups.splice(0).reverse().forEach((fn) => fn());

        const qrBox = $('#share-qr');
        if (qrBox) {
            window.FileDrop.load('/assets/vendor/qrcode.js').then(() => {
                if (!qrBox.isConnected || typeof qrcode !== 'function') return;
                const qr = qrcode(0, 'M');
                qr.addData(qrBox.dataset.url + (FDC && FDC.validKey(location.hash.slice(1)) ? location.hash : ''));
                qr.make();
                $('#share-qr-code').innerHTML = qr.createSvgTag({ cellSize: 4, scalable: true, alt: t('js.qr_alt') });
                qrBox.hidden = false;
            }).catch(() => { });
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
            const httpErrors = { 401: t('js.err.401'), 403: t('js.err.403'), 404: t('js.err.404'), 410: t('js.err.410') };
            let loaded = null, objectUrl = null;
            cleanups.push(() => { if (objectUrl) URL.revokeObjectURL(objectUrl); });

            const setDisabled = (value) => { submitBtn.disabled = value; previewBtn.disabled = value; };
            const fail = (message) => { errorLine.textContent = message; errorLine.hidden = false; status.textContent = ''; };
            const describe = (err) => {
                if (err.message === 'nokey') return t('js.err.nokey');
                if (err.status) return httpErrors[err.status] || t('js.err.download');
                if (err.message === 'format' || err.name === 'OperationError') return t('js.err.decrypt');
                return t('js.err.generic');
            };

            if (!FDC.supported()) {
                fail(t('js.err.insecure_decrypt'));
                setDisabled(true);
            }

            const load = async () => {
                if (loaded) return loaded;
                const encoded = location.hash.slice(1);
                if (!FDC.validKey(encoded)) throw new Error('nokey');

                status.textContent = t('js.downloading');
                const res = await fetch(decryptForm.action, { method: 'POST', body: new FormData(decryptForm) });
                if (!res.ok) throw Object.assign(new Error('http'), { status: res.status });
                const buffer = await res.arrayBuffer();

                status.textContent = t('js.decrypting');
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
                status.textContent = t('js.done', { name, size: fmtBytes(blob.size) });
            };

            const preview = async ({ name, blob }) => {
                if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
                previewBox.replaceChildren();

                if (blob.type === 'application/zip' || /\.zip$/i.test(name)) {
                    const box = document.createElement('div');
                    box.className = 'zip-preview';
                    previewBox.append(box);
                    previewBox.hidden = false;
                    await window.FileDropZipPreview.render(box, window.FileDropZipPreview.fromBlob(blob));
                    status.textContent = t('js.preview_status', { name, size: fmtBytes(blob.size) });
                    return;
                }

                const kind = KINDS[blob.type];
                if (!kind) {
                    previewBox.hidden = true;
                    fail(t('js.no_preview'));
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
                        note.textContent = t('js.preview_limit');
                        previewBox.append(note);
                    }
                } else {
                    objectUrl = URL.createObjectURL(blob);
                    let el;
                    if (kind === 'image') {
                        el = new Image();
                        el.src = objectUrl;
                        el.alt = t('js.preview_of', { name });
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
                        el.textContent = t('js.open_pdf');
                    }
                    previewBox.append(el);
                }

                previewBox.hidden = false;
                status.textContent = t('js.preview_status', { name, size: fmtBytes(blob.size) });
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
        if (!form) return cleanup;

        const maxSize = Number(form.dataset.maxSize);
        const maxFiles = Number(form.dataset.maxFiles) || 1;
        const FDZ = window.FileDropZip;
        const ZIP_LIMIT = FDZ ? FDZ.MAX_BYTES : maxSize;
        const input = $('#file'), drop = $('#drop'), submit = $('#submit'), list = $('#file-list');
        const progress = $('#progress'), bar = $('#bar'), fill = $('#bar-fill');
        const pct = $('#pct'), speed = $('#speed'), errorBox = $('#error');
        const result = $('#result');
        const encryptBox = $('#encrypt');
        const cryptoOk = !!(FDC && FDC.supported());
        if (!cryptoOk) {
            encryptBox.checked = false;
            encryptBox.disabled = true;
            $('#encrypt-hint').textContent = t('js.err.insecure_encrypt');
        }
        const zipHint = $('#zip-hint');
        let xhr = null, selected = [], working = false, encryptSignal = null, uploadSignal = null;

        const showError = (msg) => { errorBox.textContent = msg; errorBox.hidden = !msg; };

        const sum = (files) => files.reduce((total, f) => total + f.size, 0);
        const busy = () => !!(xhr || working);

        cleanups.push(window.FileDrop.guard(() => (busy() ? t('js.leave') : null)));
        const onUnload = (e) => { if (busy()) { e.preventDefault(); e.returnValue = ''; } };
        window.addEventListener('beforeunload', onUnload);
        cleanups.push(() => {
            window.removeEventListener('beforeunload', onUnload);
            if (uploadSignal) uploadSignal.aborted = true;
            if (xhr) xhr.abort();
            if (encryptSignal) encryptSignal.aborted = true;
        });

        const renderList = () => {
            list.replaceChildren();
            selected.forEach((file, index) => {
                const row = document.createElement('li');
                const name = document.createElement('span');
                name.className = 'item-name';
                name.textContent = file.name;
                const size = document.createElement('span');
                size.className = 'item-size';
                size.textContent = fmtBytes(file.size);
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'link';
                remove.textContent = t('js.remove');
                remove.setAttribute('aria-label', t('js.remove_file', { name: file.name }));
                remove.addEventListener('click', () => {
                    if (busy()) return;
                    selected.splice(index, 1);
                    showError('');
                    refresh();
                });
                row.append(name, size, remove);
                list.append(row);
            });
            list.hidden = selected.length === 0;
        };

        const refresh = () => {
            renderList();
            const n = selected.length;
            $('#drop-title').textContent = n === 0 ? t('js.drop_title') : (n === 1 ? selected[0].name : t('js.drop_selected', { n }));
            $('#drop-hint').textContent = n === 0
                ? t('js.drop_hint')
                : t('js.drop_more', { size: fmtBytes(sum(selected)) });
            drop.classList.toggle('has-file', n > 0);
            submit.textContent = n > 1 ? t('js.submit_many', { n }) : t('js.submit_one');
            submit.disabled = n === 0 || busy();
            zipHint.hidden = !(n > 1 && encryptBox.checked);
        };

        const addFiles = (incoming) => {
            showError('');
            const next = [...selected];
            for (const file of incoming) {
                if (file.size === 0) {
                    showError(t('js.empty_file', { name: file.name }));
                    return;
                }
                if (!next.some((f) => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified)) {
                    next.push(file);
                }
            }
            if (next.length > maxFiles) {
                showError(t('js.too_many', { max: maxFiles }));
                return;
            }
            const total = sum(next);
            const limit = next.length > 1 ? Math.min(maxSize, ZIP_LIMIT) : maxSize;
            if (total > limit) {
                showError(t(next.length > 1 ? 'js.too_big_many' : 'js.too_big_one', { total: fmtBytes(total), limit: fmtBytes(limit) }));
                return;
            }
            selected = next;
            refresh();
        };

        input.addEventListener('change', () => {
            if (!busy()) addFiles([...input.files]);
            input.value = '';
        });
        encryptBox.addEventListener('change', refresh);
        ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
        ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove('is-over'); }));
        drop.addEventListener('drop', (e) => {
            if (!busy() && e.dataTransfer.files.length) addFiles([...e.dataTransfer.files]);
        });

        const setProgress = (value, indeterminate = false) => {
            fill.style.width = value + '%';
            bar.setAttribute('aria-valuenow', String(value));
            bar.classList.toggle('is-indeterminate', indeterminate);
            pct.textContent = indeterminate ? t('js.processing') : value + '%';
        };

        const setBusy = (busy) => {
            submit.disabled = busy || selected.length === 0;
            form.querySelectorAll('input, select').forEach((el) => { el.disabled = busy || (el === encryptBox && !cryptoOk); });
            progress.hidden = !busy;
        };

        const CHUNK_RETRIES = 6;
        const storeKey = (items) => 'fd-upload:' + items.map((i) => `${i.name}:${i.blob.size}:${i.blob.lastModified || 0}`).join('|');
        const remember = (key, id) => {
            try { id ? localStorage.setItem(key, id) : localStorage.removeItem(key); } catch (_) { }
        };
        const recall = (key) => {
            try { return localStorage.getItem(key); } catch (_) { return null; }
        };
        const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

        const call = (method, url, { body = null, json = null, onProgress = null } = {}) => new Promise((resolve, reject) => {
            const req = new XMLHttpRequest();
            xhr = req;
            req.open(method, url);
            req.setRequestHeader('Accept', 'application/json');
            if (json !== null) req.setRequestHeader('Content-Type', 'application/json');
            if (onProgress) req.upload.onprogress = (ev) => { if (ev.lengthComputable) onProgress(ev.loaded); };
            req.onload = () => {
                xhr = null;
                let parsed = null;
                try { parsed = JSON.parse(req.responseText); } catch (_) { }
                resolve({ status: req.status, json: parsed });
            };
            req.onerror = () => { xhr = null; reject(Object.assign(new Error('network'), { network: true })); };
            req.onabort = () => { xhr = null; reject(Object.assign(new Error('abort'), { aborted: true })); };
            req.send(json !== null ? JSON.stringify(json) : body);
        });

        const failure = (res, fallback) => Object.assign(
            new Error((res.json && res.json.error && res.json.error.message) || fallback),
            { status: res.status },
        );

        const uploadChunked = async (items, encrypted, options, signal) => {
            const total = items.reduce((n, i) => n + i.blob.size, 0);
            const key = encrypted ? null : storeKey(items);
            const matches = (list) => Array.isArray(list) && list.length === items.length
                && list.every((f, i) => f.name === items[i].name && f.size === items[i].blob.size);

            let session = null;
            const saved = key ? recall(key) : null;
            if (saved) {
                const res = await call('GET', `/api/uploads/${saved}`);
                if (res.status === 200 && matches(res.json.data.files)) session = res.json.data;
                else remember(key, null);
            }
            if (!session) {
                const res = await call('POST', '/api/uploads', {
                    json: { files: items.map((i) => ({ name: i.name, size: i.blob.size })), encrypted },
                });
                if (res.status !== 201) throw failure(res, t('js.start_failed'));
                session = res.json.data;
                if (key) remember(key, session.id);
            }

            const received = session.files.map((f) => f.received);
            const base = received.reduce((n, r) => n + r, 0);
            let confirmed = base;
            const started = performance.now();
            const report = (inFlight) => {
                const sent = confirmed + inFlight;
                setProgress(Math.min(100, Math.round((sent / total) * 100)));
                const rate = (sent - base) / Math.max(0.25, (performance.now() - started) / 1000);
                const eta = rate > 0 ? Math.ceil((total - sent) / rate) : 0;
                speed.textContent = rate > 0 ? t(eta > 1 ? 'js.speed_eta' : 'js.speed', { rate: fmtBytes(rate), eta }) : '';
            };
            if (base > 0) speed.textContent = t('js.resuming', { size: fmtBytes(base) });
            report(0);

            for (let index = 0; index < items.length; index++) {
                const blob = items[index].blob;
                while (received[index] < blob.size) {
                    if (signal.aborted) throw Object.assign(new Error('abort'), { aborted: true });
                    const offset = received[index];
                    const end = Math.min(blob.size, offset + session.chunk_size);
                    let attempt = 0;
                    for (; ;) {
                        try {
                            const res = await call('PUT', `/api/uploads/${session.id}?index=${index}&offset=${offset}`, {
                                body: blob.slice(offset, end),
                                onProgress: report,
                            });
                            if (res.status === 200) {
                                const next = res.json.data.received;
                                confirmed += next - received[index];
                                received[index] = next;
                                report(0);
                                break;
                            }
                            if (res.status === 404) {
                                remember(key, null);
                                throw failure(res, t('js.upload_gone'));
                            }
                            if (![408, 409, 429, 500, 502, 503, 504].includes(res.status)) {
                                throw failure(res, t('js.upload_failed'));
                            }
                        } catch (err) {
                            if (err.aborted || !err.network) throw err;
                        }
                        if (++attempt > CHUNK_RETRIES) {
                            throw new Error(t('js.connection_lost'));
                        }
                        speed.textContent = t('js.retrying', { attempt, max: CHUNK_RETRIES });
                        await wait(Math.min(15000, 500 * 2 ** attempt));
                        if (signal.aborted) throw Object.assign(new Error('abort'), { aborted: true });
                        const status = await call('GET', `/api/uploads/${session.id}`).catch((err) => {
                            if (err.aborted) throw err;
                            return null;
                        });
                        if (status && status.status === 200) {
                            const fresh = status.json.data.files[index].received;
                            confirmed += fresh - received[index];
                            received[index] = fresh;
                            if (fresh !== offset) break;
                        } else if (status && status.status === 404) {
                            remember(key, null);
                            throw failure(status, t('js.upload_gone'));
                        }
                    }
                }
            }

            setProgress(100, true);
            speed.textContent = '';
            const res = await call('POST', `/api/uploads/${session.id}/complete`, { body: options });
            if (res.status !== 201 || !res.json || !res.json.data) throw failure(res, t('js.upload_failed'));
            if (key) remember(key, null);
            return res.json;
        };

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (selected.length === 0 || xhr || working) return;
            showError('');

            const files = [...selected];
            const multiple = files.length > 1;
            const data = new FormData(form);
            data.delete('encrypt');
            const local = { name: multiple ? t('js.files_count', { n: files.length }) : files[0].name, size: sum(files) };
            let keyCode = null;
            working = true;
            setBusy(true);
            setProgress(0);
            speed.textContent = '';

            if (encryptBox.checked) {
                encryptSignal = { aborted: false };
                try {
                    let source = files[0];
                    if (multiple) {
                        speed.textContent = t('js.preparing_zip');
                        const zip = await FDZ.build(files, (f) => setProgress(Math.round(f * 100)), encryptSignal);
                        source = new File([zip], t('js.archive_name'), { type: 'application/zip' });
                        setProgress(0);
                    }
                    speed.textContent = t('js.encrypting');
                    const k = await FDC.newKey();
                    const blob = await FDC.encrypt(source, k.key, (f) => setProgress(Math.round(f * 100)), encryptSignal);
                    data.set('file', new File([blob], 'encrypted.bin', { type: 'application/octet-stream' }));
                    data.set('encrypted', '1');
                    keyCode = k.encoded;
                } catch (_) {
                    const cancelled = encryptSignal.aborted;
                    encryptSignal = null;
                    working = false;
                    setBusy(false);
                    showError(cancelled ? t('js.cancelled') : t('js.encrypt_failed'));
                    return;
                }
                encryptSignal = null;
                setProgress(0);
                speed.textContent = '';
            }

            const items = encryptBox.checked
                ? [{ name: 'encrypted.bin', blob: data.get('file') }]
                : files.map((f) => ({ name: f.name, blob: f }));
            const options = new FormData();
            ['expires_in', 'password', 'max_downloads'].forEach((key) => {
                const value = data.get(key);
                if (value !== null) options.set(key, value);
            });

            uploadSignal = { aborted: false };
            try {
                const json = await uploadChunked(items, !!keyCode, options, uploadSignal);
                showResult(json.data, keyCode ? { key: keyCode, ...local } : null);
            } catch (err) {
                showError(err.aborted ? t('js.cancelled_resume') : err.message || t('js.upload_failed'));
            } finally {
                uploadSignal = null;
                xhr = null;
                working = false;
                setBusy(false);
                speed.textContent = '';
            }
        });

        $('#cancel').addEventListener('click', () => {
            if (uploadSignal) uploadSignal.aborted = true;
            if (xhr) xhr.abort();
            else if (encryptSignal) encryptSignal.aborted = true;
        });

        let uploaded = null;

        const showResult = (d, secret) => {
            uploaded = d;
            $('#code').textContent = d.code;
            $('#share-url').value = secret ? `${d.url}#${secret.key}` : d.url;
            const notes = [];
            if (secret) notes.push(t('js.note.encrypted'));
            if (d.protected) notes.push(t('js.note.protected'));
            if (d.max_downloads) notes.push(d.max_downloads === 1 ? t('js.note.once') : t('js.note.many', { n: d.max_downloads }));
            $('#result-meta').textContent = t('js.result', {
                name: `${secret ? secret.name : (d.item_count > 1 ? t('js.files_count', { n: d.item_count }) : d.name)}${d.item_count > 1 && !secret ? ' (.zip)' : ''}`,
                size: fmtBytes(secret ? secret.size : d.size),
                date: dateFmt.format(new Date(d.expires_at)),
                notes: notes.length ? ', ' + notes.join(', ') : '',
            });
            form.hidden = true;
            result.hidden = false;
            $('#copy').focus();
        };

        $('#copy').addEventListener('click', async (e) => {
            const field = $('#share-url');
            const button = e.currentTarget;
            try {
                await navigator.clipboard.writeText(field.value);
            } catch (_) {
                field.select();
                document.execCommand('copy');
            }
            button.textContent = t('js.copied');
            setTimeout(() => { button.textContent = t('js.copy'); }, 2000);
        });
        $('#share-url').addEventListener('focus', (e) => e.target.select());

        $('#again').addEventListener('click', () => {
            uploaded = null;
            form.reset();
            selected = [];
            refresh();
            result.hidden = true;
            form.hidden = false;
        });

        $('#delete').addEventListener('click', async () => {
            if (!uploaded || !confirm(t('js.confirm_delete'))) return;
            const res = await fetch('/api/files/' + uploaded.code, { method: 'DELETE', headers: { 'X-Delete-Token': uploaded.delete_token } });
            if (res.ok) {
                $('#again').click();
                showError(t('js.deleted'));
            } else {
                alert(t('js.delete_failed'));
            }
        });

        return cleanup;
    });
})();
