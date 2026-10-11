(() => {
    'use strict';

    const FD = (window.FileDrop = window.FileDrop || {});
    let data = { lang: 'it', tag: 'it-IT', messages: {} };

    try {
        data = JSON.parse(document.getElementById('fd-i18n').textContent);
    } catch (_) { }

    FD.lang = data.lang;
    FD.tag = data.tag;
    FD.t = (key, params) => {
        const text = data.messages[key];
        if (text === undefined) return key;
        if (!params) return text;
        return text.replace(/\{(\w+)\}/g, (match, name) => (name in params ? String(params[name]) : match));
    };
})();
