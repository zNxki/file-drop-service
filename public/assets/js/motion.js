(() => {
    'use strict';

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
    const ease = (t) => 1 - Math.pow(1 - t, 3);

    document.addEventListener('pointerdown', (e) => {
        if (reduced.matches || !e.target.closest) return;
        const btn = e.target.closest('.btn');
        if (!btn || btn.disabled || btn.getAttribute('aria-disabled') === 'true') return;
        const rect = btn.getBoundingClientRect();
        const dot = document.createElement('span');
        dot.className = 'ripple';
        dot.style.left = (e.clientX - rect.left) + 'px';
        dot.style.top = (e.clientY - rect.top) + 'px';
        btn.append(dot);
        dot.addEventListener('animationend', () => dot.remove(), { once: true });
        setTimeout(() => dot.remove(), 1000);
    });

    let tilted = null, frame = 0;
    const untilt = () => {
        if (!tilted) return;
        tilted.style.transition = '';
        tilted.style.transform = '';
        tilted = null;
    };
    document.addEventListener('pointermove', (e) => {
        if (reduced.matches || !finePointer.matches || e.pointerType === 'touch' || !e.target.closest) return;
        const ticket = e.target.closest('.ticket');
        if (ticket !== tilted) untilt();
        if (!ticket) return;
        tilted = ticket;
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            const r = ticket.getBoundingClientRect();
            const x = (e.clientX - r.left) / r.width - 0.5;
            const y = (e.clientY - r.top) / r.height - 0.5;
            ticket.style.transition = 'transform 0.15s ease-out';
            ticket.style.transform = `perspective(900px) rotateX(${(-y * 6).toFixed(2)}deg) rotateY(${(x * 8).toFixed(2)}deg)`;
        });
    }, { passive: true });
    document.documentElement.addEventListener('mouseleave', untilt);

    document.addEventListener('pointermove', (e) => {
        if (reduced.matches || !e.target.closest) return;
        const card = e.target.closest('.stat, .panel');
        if (!card) return;
        const r = card.getBoundingClientRect();
        card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
        card.style.setProperty('--my', (e.clientY - r.top) + 'px');
    }, { passive: true });

    const confetti = (origin) => {
        if (reduced.matches || !origin.animate) return;
        const r = origin.getBoundingClientRect();
        const colors = ['#ffd23f', '#0b6e69', '#5cd6c9', '#ff7a59', '#ffffff'];
        const layer = document.createElement('div');
        layer.className = 'confetti';
        document.body.append(layer);
        for (let i = 0; i < 40; i++) {
            const p = document.createElement('i');
            const size = 6 + Math.random() * 6;
            p.style.cssText = `left:${r.left + r.width / 2}px;top:${r.top + 40}px;width:${size}px;height:${size * 0.45}px;background:${colors[i % colors.length]}`;
            layer.append(p);
            const angle = -Math.random() * Math.PI;
            const speed = 140 + Math.random() * 230;
            const dx = Math.cos(angle) * speed;
            const up = Math.sin(angle) * speed;
            const fall = 240 + Math.random() * 280;
            p.animate([
                { transform: 'translate(0, 0) rotate(0deg)', opacity: 1 },
                { transform: `translate(${dx * 0.6}px, ${up}px) rotate(${Math.random() * 360}deg)`, opacity: 1, offset: 0.4 },
                { transform: `translate(${dx}px, ${up + fall}px) rotate(${360 + Math.random() * 540}deg)`, opacity: 0 },
            ], { duration: 1300 + Math.random() * 800, easing: 'cubic-bezier(0.2, 0.7, 0.4, 1)', fill: 'forwards' });
        }
        setTimeout(() => layer.remove(), 2400);
    };
    const countUp = (node) => {
        const raw = node.textContent.trim();
        if (!/^\d[\d.,\u00a0\s]*$/.test(raw)) return;
        const target = parseInt(raw.replace(/\D/g, ''), 10);
        if (!Number.isFinite(target) || target < 2) return;
        const sep = (raw.match(/[.,\u00a0\s]/) || [''])[0];
        const fmt = (n) => (sep ? String(n).replace(/\B(?=(\d{3})+(?!\d))/g, sep) : String(n));
        const start = performance.now();
        const tick = (now) => {
            const p = Math.min(1, (now - start) / 1000);
            node.textContent = p < 1 ? fmt(Math.round(target * ease(p))) : raw;
            if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };
    window.FileDrop.page(() => {
        const timers = [];

        document.querySelectorAll('.flash:not(.flash-error)').forEach((flash) => {
            timers.push(setTimeout(() => {
                if (reduced.matches) return flash.remove();
                flash.classList.add('is-leaving');
                timers.push(setTimeout(() => flash.remove(), 650));
            }, 6000));
        });

        let observer = null;
        const result = document.querySelector('#result');
        if (result) {
            observer = new MutationObserver(() => {
                if (!result.hidden) timers.push(setTimeout(() => confetti(result.querySelector('.ticket-main') || result), 350));
            });
            observer.observe(result, { attributes: true, attributeFilter: ['hidden'] });
        }

        if (!reduced.matches) document.querySelectorAll('.stat-value').forEach(countUp);

        return () => {
            timers.forEach(clearTimeout);
            if (observer) observer.disconnect();
            document.querySelectorAll('.confetti').forEach((n) => n.remove());
        };
    });
})();
