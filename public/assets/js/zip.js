(() => {
    'use strict';

    const MAX_BYTES = 4000000000;
    const READ = 4194304;

    const TABLE = (() => {
        const t = new Uint32Array(256);
        for (let n = 0; n < 256; n++) {
            let c = n;
            for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1;
            t[n] = c >>> 0;
        }
        return t;
    })();

    const crcOf = async (file, onBytes, signal) => {
        let crc = 0xFFFFFFFF;
        for (let offset = 0; offset < file.size; offset += READ) {
            if (signal && signal.aborted) throw new Error('aborted');
            const bytes = new Uint8Array(await file.slice(offset, offset + READ).arrayBuffer());
            for (let i = 0; i < bytes.length; i++) crc = TABLE[(crc ^ bytes[i]) & 0xFF] ^ (crc >>> 8);
            onBytes(bytes.length);
        }
        return (crc ^ 0xFFFFFFFF) >>> 0;
    };

    const uniqueNames = (names) => {
        const taken = new Set();
        return names.map((name) => {
            const dot = name.lastIndexOf('.');
            const base = dot > 0 ? name.slice(0, dot) : name;
            const ext = dot > 0 ? name.slice(dot) : '';
            let candidate = name;
            for (let n = 2; taken.has(candidate.toLowerCase()); n++) candidate = `${base} (${n})${ext}`;
            taken.add(candidate.toLowerCase());
            return candidate;
        });
    };

    const dosStamp = (ms) => {
        const d = new Date(ms || Date.now());
        const year = Math.min(2107, Math.max(1980, d.getFullYear()));
        return {
            date: ((year - 1980) << 9) | ((d.getMonth() + 1) << 5) | d.getDate(),
            time: (d.getHours() << 11) | (d.getMinutes() << 5) | (d.getSeconds() >> 1),
        };
    };

    const build = async (files, onProgress, signal) => {
        const total = files.reduce((sum, f) => sum + f.size, 0);
        if (total > MAX_BYTES) throw new Error('too-large');

        const encoder = new TextEncoder();
        const names = uniqueNames(files.map((f) => f.name));
        const parts = [];
        const central = [];
        let offset = 0;
        let done = 0;

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const name = encoder.encode(names[i]);
            const crc = await crcOf(file, (n) => {
                done += n;
                if (onProgress) onProgress(done / Math.max(1, total));
            }, signal);
            const { date, time } = dosStamp(file.lastModified);

            const local = new Uint8Array(30 + name.length);
            const v = new DataView(local.buffer);
            v.setUint32(0, 0x04034B50, true);
            v.setUint16(4, 20, true);
            v.setUint16(6, 0x0800, true); // nomi in UTF-8
            v.setUint16(8, 0, true);      // stored
            v.setUint16(10, time, true);
            v.setUint16(12, date, true);
            v.setUint32(14, crc, true);
            v.setUint32(18, file.size, true);
            v.setUint32(22, file.size, true);
            v.setUint16(26, name.length, true);
            v.setUint16(28, 0, true);
            local.set(name, 30);

            parts.push(local, file);
            central.push({ name, crc, size: file.size, date, time, offset });
            offset += local.length + file.size;
        }

        let centralSize = 0;
        for (const c of central) {
            const header = new Uint8Array(46 + c.name.length);
            const v = new DataView(header.buffer);
            v.setUint32(0, 0x02014B50, true);
            v.setUint16(4, 20, true);
            v.setUint16(6, 20, true);
            v.setUint16(8, 0x0800, true);
            v.setUint16(10, 0, true);
            v.setUint16(12, c.time, true);
            v.setUint16(14, c.date, true);
            v.setUint32(16, c.crc, true);
            v.setUint32(20, c.size, true);
            v.setUint32(24, c.size, true);
            v.setUint16(28, c.name.length, true);
            v.setUint32(42, c.offset, true);
            header.set(c.name, 46);
            parts.push(header);
            centralSize += header.length;
        }

        const end = new Uint8Array(22);
        const v = new DataView(end.buffer);
        v.setUint32(0, 0x06054B50, true);
        v.setUint16(8, central.length, true);
        v.setUint16(10, central.length, true);
        v.setUint32(12, centralSize, true);
        v.setUint32(16, offset, true);
        parts.push(end);

        return new Blob(parts, { type: 'application/zip' });
    };

    window.FileDropZip = { build, uniqueNames, MAX_BYTES };
})();
