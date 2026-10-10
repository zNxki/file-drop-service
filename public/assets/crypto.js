(() => {
    'use strict';

    const MAGIC = [0x46, 0x44, 0x45, 0x31];
    const HEADER_LEN = 11;
    const NONCE_LEN = 7;
    const CHUNK = 1048576;
    const META = 1024;
    const TAG = 16;
    const META_SEALED = META + TAG;
    const KEY_PATTERN = /^[A-Za-z0-9_-]{43}$/;

    const supported = () => !!(window.crypto && window.crypto.subtle && window.isSecureContext !== false);

    const encode = (bytes) => btoa(String.fromCharCode(...bytes))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');

    const decode = (text) => {
        const padded = text.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - (text.length % 4)) % 4);
        return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
    };

    const validKey = (text) => typeof text === 'string' && KEY_PATTERN.test(text);

    const newKey = async () => {
        const key = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt']);
        const raw = new Uint8Array(await crypto.subtle.exportKey('raw', key));
        return { key, encoded: encode(raw) };
    };

    const importKey = (text, usage) => {
        if (!validKey(text)) throw new Error('invalid-key');
        return crypto.subtle.importKey('raw', decode(text), 'AES-GCM', false, [usage]);
    };

    const iv = (nonce, index, last) => {
        const out = new Uint8Array(12);
        out.set(nonce, 0);
        new DataView(out.buffer).setUint32(NONCE_LEN, index);
        out[11] = last ? 1 : 0;
        return out;
    };

    const packMeta = (file) => {
        const type = (file.type || 'application/octet-stream').slice(0, 100);
        let name = file.name;
        for (; ;) {
            const body = new TextEncoder().encode(JSON.stringify({ name, type }));
            if (body.length <= META - 4) {
                const out = new Uint8Array(META);
                new DataView(out.buffer).setUint32(0, body.length);
                out.set(body, 4);
                return out;
            }
            name = name.slice(0, Math.max(1, Math.floor(name.length / 2)));
        }
    };

    const encrypt = async (file, key, onProgress, signal) => {
        const nonce = crypto.getRandomValues(new Uint8Array(NONCE_LEN));
        const header = new Uint8Array(HEADER_LEN);
        header.set(MAGIC, 0);
        header.set(nonce, 4);

        const seal = async (index, last, data) => new Uint8Array(await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv: iv(nonce, index, last), additionalData: header, tagLength: 128 },
            key,
            data
        ));

        const parts = [header, await seal(0, false, packMeta(file))];
        const total = Math.max(1, Math.ceil(file.size / CHUNK));
        for (let i = 0; i < total; i++) {
            if (signal && signal.aborted) throw new Error('aborted');
            const slice = new Uint8Array(await file.slice(i * CHUNK, (i + 1) * CHUNK).arrayBuffer());
            parts.push(await seal(i + 1, i === total - 1, slice));
            if (onProgress) onProgress((i + 1) / total);
        }

        return new Blob(parts, { type: 'application/octet-stream' });
    };

    const decrypt = async (buffer, key, onProgress) => {
        const data = new Uint8Array(buffer);
        if (data.length < HEADER_LEN + META_SEALED + TAG || MAGIC.some((b, i) => data[i] !== b)) {
            throw new Error('format');
        }

        const header = data.subarray(0, HEADER_LEN);
        const nonce = header.subarray(4, HEADER_LEN);
        const open = async (index, last, chunk) => new Uint8Array(await crypto.subtle.decrypt(
            { name: 'AES-GCM', iv: iv(nonce, index, last), additionalData: header, tagLength: 128 },
            key,
            chunk
        ));

        const meta = await open(0, false, data.subarray(HEADER_LEN, HEADER_LEN + META_SEALED));
        const length = new DataView(meta.buffer, meta.byteOffset, meta.byteLength).getUint32(0);
        if (length > META - 4) throw new Error('format');
        const info = JSON.parse(new TextDecoder().decode(meta.subarray(4, 4 + length)));

        const parts = [];
        let offset = HEADER_LEN + META_SEALED;
        let index = 1;
        while (offset < data.length) {
            const end = Math.min(offset + CHUNK + TAG, data.length);
            parts.push(await open(index++, end === data.length, data.subarray(offset, end)));
            offset = end;
            if (onProgress) onProgress(offset / data.length);
        }

        return { name: String(info.name || 'file'), blob: new Blob(parts, { type: String(info.type || 'application/octet-stream') }) };
    };

    window.FileDropCrypto = { supported, validKey, newKey, importKey, encrypt, decrypt };
})();