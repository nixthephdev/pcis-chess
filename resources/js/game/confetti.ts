const COLORS = ['#ffd23f', '#ff8a3d', '#e8547a', '#3fae73', '#1c2541', '#ffffff'];

export function confetti() {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;

    const cv = document.createElement('canvas');
    cv.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:60';
    document.body.appendChild(cv);
    const c = cv.getContext('2d');
    if (!c) return cv.remove();

    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const W = window.innerWidth;
    const H = window.innerHeight;
    cv.width = W * dpr;
    cv.height = H * dpr;
    c.scale(dpr, dpr);

    const bits = Array.from({ length: 140 }, () => ({
        x: W / 2 + (Math.random() - 0.5) * W * 0.3,
        y: H * 0.35,
        vx: (Math.random() - 0.5) * 14,
        vy: -Math.random() * 14 - 4,
        s: 6 + Math.random() * 6,
        r: Math.random() * 6,
        vr: (Math.random() - 0.5) * 0.3,
        color: COLORS[Math.floor(Math.random() * COLORS.length)],
    }));

    let frame = 0;
    const tick = () => {
        c.clearRect(0, 0, W, H);
        for (const p of bits) {
            p.vy += 0.35;
            p.x += p.vx;
            p.y += p.vy;
            p.vx *= 0.99;
            p.r += p.vr;
            c.save();
            c.translate(p.x, p.y);
            c.rotate(p.r);
            c.fillStyle = p.color;
            c.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2);
            c.restore();
        }
        if (++frame < 120) requestAnimationFrame(tick);
        else cv.remove();
    };
    requestAnimationFrame(tick);
}
