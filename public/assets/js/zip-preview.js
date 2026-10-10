(() => {
    'use strict';

    const TEXT_LIMIT = 65536;
    const MEDIA_LIMIT = 25 * 1024 * 1024;
    const DIR_LIMIT = 8 * 1024 * 1024;
    const TEXT_READ = 1024 * 1024;

    const IMG = { png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif', webp: 'image/webp', avif: 'image/avif', svg: 'image/svg+xml', bmp: 'image/bmp', ico: 'image/x-icon' };
    const VID = { mp4: 'video/mp4', webm: 'video/webm', ogv: 'video/ogg' };
    const BIN = new Set(['zip', 'gz', 'tar', '7z', 'rar', 'exe', 'dll', 'so', 'bin', 'woff', 'woff2', 'ttf', 'otf', 'mp3', 'wav', 'sqlite', 'db', 'class', 'jar', 'psd', 'iso']);

    const fmtBytes = (n) => {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
        return n.toLocaleString('it-IT', { maximumFractionDigits: i ? 1 : 0 }) + ' ' + units[i];
    };

    const el = (tag, cls, text) => {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const fromBlob = (blob) => ({
        size: blob.size,
        read: async (s, e) => new Uint8Array(await blob.slice(s, e).arrayBuffer()),
    });

    const fromUrl = (url, size) => ({
        size,
        read: async (s, e) => {
            const res = await fetch(url, { headers: { Range: `bytes=${s}-${e - 1}` }, credentials: 'same-origin' });
            if (!res.ok) throw new Error('Impossibile leggere l\'archivio.');
            const buf = new Uint8Array(await res.arrayBuffer());
            return res.status === 200 ? buf.slice(s, e) : buf;
        },
    });

    const readDirectory = async (src) => {
        if (src.size < 22) throw new Error('Archivio zip non valido.');
        const tail = await src.read(Math.max(0, src.size - 65557), src.size);
        const tv = new DataView(tail.buffer, tail.byteOffset, tail.byteLength);
        let p = tail.length - 22;
        while (p >= 0 && tv.getUint32(p, true) !== 0x06054B50) p--;
        if (p < 0) throw new Error('Archivio zip non valido.');

        const count = tv.getUint16(p + 10, true);
        const cdSize = tv.getUint32(p + 12, true);
        const cdOff = tv.getUint32(p + 16, true);
        if (count === 0xFFFF || cdSize === 0xFFFFFFFF || cdOff === 0xFFFFFFFF) {
            throw new Error('Gli archivi ZIP64 non hanno ancora l\'anteprima.');
        }
        if (cdSize > DIR_LIMIT) throw new Error('L\'elenco dei file è troppo grande per l\'anteprima.');

        const cd = await src.read(cdOff, cdOff + cdSize);
        const dv = new DataView(cd.buffer, cd.byteOffset, cd.byteLength);
        const decoder = new TextDecoder('utf-8');
        const entries = [];
        let o = 0;
        while (o + 46 <= cd.length && dv.getUint32(o, true) === 0x02014B50 && entries.length < count) {
            const nameLen = dv.getUint16(o + 28, true);
            const extraLen = dv.getUint16(o + 30, true);
            const commentLen = dv.getUint16(o + 32, true);
            const name = decoder.decode(cd.subarray(o + 46, o + 46 + nameLen)).replace(/\\/g, '/');
            entries.push({
                name,
                flags: dv.getUint16(o + 8, true),
                method: dv.getUint16(o + 10, true),
                csize: dv.getUint32(o + 20, true),
                size: dv.getUint32(o + 24, true),
                offset: dv.getUint32(o + 42, true),
                dir: name.endsWith('/'),
            });
            o += 46 + nameLen + extraLen + commentLen;
        }
        return entries;
    };

    const inflate = async (bytes, limit) => {
        const ds = new DecompressionStream('deflate-raw');
        const writer = ds.writable.getWriter();
        writer.write(bytes).catch(() => { });
        writer.close().catch(() => { });
        const reader = ds.readable.getReader();
        const chunks = [];
        let total = 0;
        try {
            while (total < limit) {
                const { done, value } = await reader.read();
                if (done) break;
                chunks.push(value);
                total += value.length;
            }
        } catch (err) {
            if (!total) throw err;
        }
        reader.cancel().catch(() => { });
        const out = new Uint8Array(Math.min(total, limit));
        let pos = 0;
        for (const c of chunks) {
            if (pos >= out.length) break;
            out.set(c.subarray(0, out.length - pos), pos);
            pos += c.length;
        }
        return out;
    };

    const readEntry = async (src, entry, limit, readCap) => {
        const lh = await src.read(entry.offset, entry.offset + 30);
        const v = new DataView(lh.buffer, lh.byteOffset, lh.byteLength);
        if (lh.length < 30 || v.getUint32(0, true) !== 0x04034B50) throw new Error('Voce dell\'archivio danneggiata.');
        const start = entry.offset + 30 + v.getUint16(26, true) + v.getUint16(28, true);
        if (entry.method === 0) return src.read(start, start + Math.min(entry.csize, limit));
        const data = await src.read(start, start + Math.min(entry.csize, readCap));
        return inflate(data, limit);
    };

    const open = async (src) => {
        const entries = await readDirectory(src);
        return { entries, read: (entry, limit, cap) => readEntry(src, entry, limit, cap) };
    };

    const buildTree = (entries) => {
        const root = { name: '', dir: true, children: new Map() };
        for (const entry of entries) {
            const parts = entry.name.split('/').filter((s) => s && s !== '.');
            let node = root;
            parts.forEach((part, i) => {
                const last = i === parts.length - 1;
                if (!node.children.has(part)) node.children.set(part, { name: part, dir: !last || entry.dir, children: new Map() });
                node = node.children.get(part);
                if (last && !entry.dir) node.entry = entry;
            });
        }
        return root;
    };

    const kindOf = (name) => {
        const base = name.split('/').pop().toLowerCase();
        const ext = base.includes('.') ? base.split('.').pop() : '';
        if (IMG[ext]) return { kind: 'image', mime: IMG[ext] };
        if (VID[ext]) return { kind: 'video', mime: VID[ext] };
        if (ext === 'pdf') return { kind: 'pdf', mime: 'application/pdf' };
        if (BIN.has(ext)) return { kind: 'none' };
        return { kind: 'text' };
    };

    const render = async (host, src) => {
        host.hidden = false;
        host.replaceChildren(el('p', 'preview-note', 'Lettura dell\'archivio…'));

        let archive;
        try {
            if (typeof DecompressionStream === 'undefined') throw new Error('Il browser non supporta l\'anteprima degli zip.');
            archive = await open(src);
        } catch (err) {
            host.replaceChildren(el('p', 'preview-note', err.message || 'Impossibile leggere l\'archivio.'));
            return;
        }

        const files = archive.entries.filter((e) => !e.dir);
        const total = files.reduce((s, e) => s + e.size, 0);
        const root = buildTree(archive.entries);

        const tree = el('div', 'zp-tree');
        const viewer = el('div', 'zp-viewer');
        let current = null, objectUrl = null, seq = 0;

        const placeholder = () => viewer.replaceChildren(el('p', 'preview-note', 'Seleziona un file per vedere l\'anteprima.'));

        const show = async (entry, button) => {
            if (current) current.removeAttribute('aria-current');
            current = button;
            button.setAttribute('aria-current', 'true');
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
            const token = ++seq;

            const head = el('div', 'zp-head');
            head.append(el('strong', 'zp-title', entry.name), el('span', 'zp-size', fmtBytes(entry.size)));
            const body = el('div', 'zp-body');
            body.append(el('p', 'preview-note', 'Caricamento…'));
            viewer.replaceChildren(head, body);
            const setBody = (...nodes) => { if (token === seq) body.replaceChildren(...nodes); };
            const note = (text) => setBody(el('p', 'preview-note', text));

            try {
                if (entry.flags & 1) return note('Il file è protetto da password: anteprima non disponibile.');
                if (entry.method !== 0 && entry.method !== 8) return note('Compressione non supportata per l\'anteprima.');
                if (entry.size === 0) return note('File vuoto.');
                const { kind, mime } = kindOf(entry.name);
                if (kind === 'none') return note('Anteprima non disponibile per questo tipo di file.');

                if (kind === 'text') {
                    const bytes = await archive.read(entry, TEXT_LIMIT, TEXT_READ);
                    if (token !== seq) return;
                    if (bytes.subarray(0, 4096).includes(0)) return note('File binario: anteprima non disponibile.');
                    const text = new TextDecoder('utf-8').decode(bytes);
                    const pre = el('pre', 'preview-text');
                    const colored = window.FileDropHighlight && window.FileDropHighlight.render(text, entry.name);
                    if (colored) pre.append(colored); else pre.textContent = text;
                    const nodes = [pre];
                    if (entry.size > TEXT_LIMIT) nodes.push(el('p', 'preview-note', 'Anteprima limitata ai primi 64 KB.'));
                    return setBody(...nodes);
                }

                if (entry.size > MEDIA_LIMIT || entry.csize > MEDIA_LIMIT) return note('File troppo grande per l\'anteprima (oltre 25 MB).');
                const bytes = await archive.read(entry, MEDIA_LIMIT, MEDIA_LIMIT);
                if (token !== seq) return;
                objectUrl = URL.createObjectURL(new Blob([bytes], { type: mime }));
                let node;
                if (kind === 'image') {
                    node = new Image();
                    node.src = objectUrl;
                    node.alt = `Anteprima di ${entry.name}`;
                } else if (kind === 'video') {
                    node = el('video');
                    node.src = objectUrl;
                    node.controls = true;
                    node.preload = 'metadata';
                    node.playsInline = true;
                } else {
                    node = el('a', 'btn btn-small', 'Apri anteprima PDF');
                    node.href = objectUrl;
                    node.target = '_blank';
                    node.rel = 'noopener';
                }
                setBody(node);
            } catch (err) {
                note('Impossibile leggere questo file dall\'archivio.');
            }
        };

        const list = (node, level) => {
            const ul = el('ul', 'zp-list');
            ul.setAttribute('role', level === 0 ? 'tree' : 'group');
            const children = [...node.children.values()].sort((a, b) => (b.dir - a.dir) || a.name.localeCompare(b.name, 'it', { numeric: true }));
            for (const child of children) {
                const li = el('li');
                li.setAttribute('role', 'treeitem');
                const btn = el('button', child.dir ? 'zp-row zp-dir' : 'zp-row zp-file');
                btn.type = 'button';
                btn.style.setProperty('--level', String(level));
                btn.append(el('span', 'zp-icon'), el('span', 'zp-name', child.name));
                if (child.dir) {
                    const sub = list(child, level + 1);
                    const open = level === 0 || files.length <= 40;
                    sub.hidden = !open;
                    btn.setAttribute('aria-expanded', String(open));
                    btn.addEventListener('click', () => {
                        const next = sub.hidden;
                        sub.hidden = !next;
                        btn.setAttribute('aria-expanded', String(next));
                    });
                    li.append(btn, sub);
                } else {
                    if (child.entry) btn.append(el('span', 'zp-size', fmtBytes(child.entry.size)));
                    btn.addEventListener('click', () => show(child.entry, btn));
                    li.append(btn);
                }
                ul.append(li);
            }
            return ul;
        };

        tree.append(list(root, 0));
        const summary = el('p', 'preview-note zp-summary', `${files.length} ${files.length === 1 ? 'file' : 'file'} · ${fmtBytes(total)} decompressi`);
        const grid = el('div', 'zp-grid');
        grid.append(tree, viewer);
        placeholder();

        const expandBtn = el('button', 'btn btn-secondary btn-small', 'Allarga');
        expandBtn.type = 'button';
        expandBtn.setAttribute('aria-expanded', 'false');
        const toolbar = el('div', 'zp-toolbar');
        toolbar.append(summary, expandBtn);

        let overlay = null, marker = null;
        const onKey = (e) => { if (e.key === 'Escape') collapse(); };
        const collapse = () => {
            if (!overlay) return;
            document.removeEventListener('keydown', onKey);
            marker.replaceWith(host);
            overlay.remove();
            overlay = marker = null;
            host.classList.remove('is-expanded');
            document.documentElement.classList.remove('zp-lock');
            expandBtn.textContent = 'Allarga';
            expandBtn.setAttribute('aria-expanded', 'false');
            expandBtn.focus();
        };
        const expand = () => {
            marker = document.createComment('zip-preview');
            host.parentNode.insertBefore(marker, host);
            overlay = el('div', 'zp-overlay');
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-label', 'Anteprima archivio');
            overlay.addEventListener('mousedown', (e) => { if (e.target === overlay) collapse(); });
            host.classList.add('is-expanded');
            overlay.append(host);
            document.body.append(overlay);
            document.documentElement.classList.add('zp-lock');
            document.addEventListener('keydown', onKey);
            if (window.FileDrop) window.FileDrop.cleanup(collapse); // se si cambia pagina con l'anteprima allargata
            expandBtn.textContent = 'Chiudi';
            expandBtn.setAttribute('aria-expanded', 'true');
            expandBtn.focus();
        };
        expandBtn.addEventListener('click', () => (overlay ? collapse() : expand()));

        host.replaceChildren(toolbar, grid);
    };

    window.FileDropZipPreview = { render, fromBlob, fromUrl, open };

    if (window.FileDrop) {
        window.FileDrop.page(() => {
            const host = document.querySelector('#zip-preview');
            if (host) render(host, fromUrl(host.dataset.src, Number(host.dataset.size)));
        });
    }
})();
