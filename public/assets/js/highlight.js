(() => {
    'use strict';

    const Y = (re, extra = '') => new RegExp(re.source, 'y' + extra + re.flags);
    const set = (s) => new Set(s ? s.split(' ') : []);
    const STR = /"(?:[^"\\\n]|\\.)*"?|'(?:[^'\\\n]|\\.)*'?/;
    const NUM = /\b(?:0x[\da-f]+|\d+(?:\.\d+)?(?:e[+-]?\d+)?)\b/i;
    const WS = { re: Y(/\s+/), cls: null };

    const identRule = (o) => ({
        re: Y(o.ident || /[A-Za-z_$][\w$]*/),
        cls: (m, after) => {
            const w = o.ci ? m[0].toLowerCase() : m[0];
            if (o.kw.has(w)) return 'kw';
            if (o.lit.has(w)) return 'lit';
            if (!o.noFn && /^\s*\(/.test(after)) return 'fn';
            if (o.key && o.key.test(after)) return 'prop';
            if (o.types && /^[A-Z]/.test(m[0])) return 'type';
            return null;
        },
    });

    const code = (o) => {
        o.kw = set(o.kw);
        o.lit = set(o.lit);
        const rules = [WS];
        if (o.tags) rules.push({ re: Y(o.tags), cls: 'kw' });
        if (o.triple) rules.push({ re: Y(/"""[\s\S]*?(?:"""|$)|'''[\s\S]*?(?:'''|$)/), cls: 'str' });
        if (o.block) rules.push({ re: Y(/\/\*[\s\S]*?(?:\*\/|$)/), cls: 'com' });
        if (o.line) rules.push({ re: Y(new RegExp('(?:' + o.line + ')[^\\n]*')), cls: 'com' });
        if (o.tpl) rules.push({ re: Y(/`(?:[^`\\]|\\[\s\S])*`?/), cls: 'str' });
        rules.push({ re: Y(STR), cls: 'str' });
        if (o.variable) rules.push({ re: Y(o.variable), cls: 'var' });
        rules.push(identRule(o));
        rules.push({ re: Y(NUM), cls: 'num' });
        return rules;
    };

    const JS = 'abstract as async await break case catch class const continue debugger default delete do else enum export extends finally for from function get if implements import in instanceof interface let new of package private protected public readonly return set static super switch throw try type typeof var void while with yield';
    const PHP = 'abstract and array as break case catch class clone const continue declare default do echo else elseif empty enddeclare endfor endforeach endif endswitch endwhile enum extends final finally fn for foreach function global goto if implements include include_once instanceof insteadof interface isset list match namespace new or print private protected public readonly require require_once return static switch throw trait try unset use var while xor yield';
    const SQL = 'select from where insert into values update set delete create table alter drop index join left right inner outer on group by order having limit offset as and or not is in like distinct union all primary key foreign references default unique integer text real blob varchar int begin commit exists case when then else end';
    const CLIKE = 'abstract break case catch char class const continue default do double else enum extends final finally float for func fn go if implements import int interface let long mod mut namespace new package private protected pub public return short static struct super switch this throw throws try type use using var void while impl trait match fun val';

    const js = code({ kw: JS, lit: 'true false null undefined NaN Infinity this', block: true, line: '\\/\\/', tpl: true, types: true });
    const clike = code({ kw: CLIKE, lit: 'true false null nil None', block: true, line: '\\/\\/', types: true });
    const ini = (hash) => {
        const o = { kw: '', lit: 'true false on off yes no null', ident: /[A-Za-z_.][\w.-]*/, key: /^\s*[=:]/, noFn: true };
        const base = code({ ...o, line: hash });
        base.splice(1, 0, { re: Y(/^\[[^\]\n]*\]/, 'm'), cls: 'type' });
        return base;
    };

    const LANGS = {
        js,
        clike,
        php: code({ kw: PHP, lit: 'true false null self parent', block: true, line: '\\/\\/|#', variable: /\$[A-Za-z_]\w*/, ident: /[A-Za-z_]\w*/, ci: true, types: true, tags: /<\?php|<\?=|\?>/ }),
        py: code({ kw: 'and as assert async await break class continue def del elif else except finally for from global if import in is lambda nonlocal not or pass raise return try while with yield', lit: 'True False None self', line: '#', triple: true, ident: /[A-Za-z_]\w*/, types: true }),
        sh: code({ kw: 'if then else elif fi for while do done case esac in function select until return exit local export readonly unset echo cd', lit: '', line: '#', variable: /\$(?:\{[^}\n]*\}|[A-Za-z_]\w*|[0-9@#?*$!-])/, ident: /[A-Za-z_][\w-]*/, noFn: true }),
        sql: code({ kw: SQL, lit: 'null true false', line: '--', block: true, ident: /[A-Za-z_]\w*/, ci: true }),
        ini: ini('[;#]'),
        yaml: code({ kw: '', lit: 'true false null yes no', line: '#', ident: /[A-Za-z_][\w.-]*/, key: /^\s*:(?:\s|$)/, noFn: true }),
        css: [
            WS,
            { re: Y(/\/\*[\s\S]*?(?:\*\/|$)/), cls: 'com' },
            { re: Y(STR), cls: 'str' },
            { re: Y(/@[\w-]+/), cls: 'kw' },
            { re: Y(/--[\w-]+/), cls: 'var' },
            { re: Y(/-?[A-Za-z_][\w-]*/), cls: (m, after) => (/^\s*:\s/.test(after) ? 'prop' : (/^\s*\(/.test(after) ? 'fn' : null)) },
            { re: Y(/#[\da-f]{3,8}\b|\d*\.?\d+(?:[a-z%]+)?/i), cls: 'num' },
        ],
        json: [
            WS,
            { re: Y(STR), cls: (m, after) => (/^\s*:/.test(after) ? 'prop' : 'str') },
            { re: Y(/[A-Za-z_]\w*/), cls: (m) => (/^(?:true|false|null)$/.test(m[0]) ? 'lit' : null) },
            { re: Y(NUM), cls: 'num' },
        ],
        markup: [
            WS,
            { re: Y(/<!--[\s\S]*?(?:-->|$)/), cls: 'com' },
            { re: Y(/<[!?\/]?[A-Za-z][\w:-]*|<!DOCTYPE/i), cls: 'kw' },
            { re: Y(/"[^"]*"?|'[^']*'?/), cls: 'str' },
            { re: Y(/[A-Za-z_:@][\w:.@-]*(?=\s*=)/), cls: 'prop' },
            { re: Y(/&#?\w+;/), cls: 'num' },
        ],
        md: [
            { re: Y(/^#{1,6}[^\n]*/, 'm'), cls: 'kw' },
            { re: Y(/```[\s\S]*?(?:```|$)/), cls: 'str' },
            { re: Y(/`[^`\n]+`/), cls: 'str' },
            { re: Y(/\[[^\]\n]*\]\([^)\n]*\)/), cls: 'fn' },
        ],
    };

    const EXT = {
        js: 'js', mjs: 'js', cjs: 'js', jsx: 'js', ts: 'js', tsx: 'js',
        php: 'php', phtml: 'php', py: 'py', sh: 'sh', bash: 'sh', zsh: 'sh', makefile: 'sh', dockerfile: 'sh',
        sql: 'sql', css: 'css', scss: 'css', less: 'css', json: 'json', lock: 'json', map: 'json',
        yml: 'yaml', yaml: 'yaml', html: 'markup', htm: 'markup', xml: 'markup', svg: 'markup', vue: 'markup',
        md: 'md', markdown: 'md', ini: 'ini', env: 'ini', toml: 'ini', conf: 'ini', htaccess: 'ini', cfg: 'ini',
        java: 'clike', c: 'clike', h: 'clike', cpp: 'clike', cs: 'clike', go: 'clike', rs: 'clike', kt: 'clike', swift: 'clike',
    };

    const langOf = (name) => {
        const base = name.split('/').pop().toLowerCase();
        return LANGS[EXT[base.includes('.') ? base.split('.').pop() : base]] || null;
    };

    const render = (text, name) => {
        const rules = langOf(name);
        if (!rules || text.length > 200000) return null;

        const frag = document.createDocumentFragment();
        let plain = '';
        const flush = () => { if (plain) { frag.append(plain); plain = ''; } };

        let i = 0;
        const n = text.length;
        while (i < n) {
            let hit = false;
            for (const rule of rules) {
                rule.re.lastIndex = i;
                const m = rule.re.exec(text);
                if (!m || !m[0].length) continue;
                const end = i + m[0].length;
                const cls = typeof rule.cls === 'function' ? rule.cls(m, text.slice(end, end + 24)) : rule.cls;
                if (cls) {
                    flush();
                    const span = document.createElement('span');
                    span.className = 'hl-' + cls;
                    span.textContent = m[0];
                    frag.append(span);
                } else {
                    plain += m[0];
                }
                i = end;
                hit = true;
                break;
            }
            if (!hit) plain += text[i++];
        }
        flush();
        return frag;
    };

    window.FileDropHighlight = { render };
})();
