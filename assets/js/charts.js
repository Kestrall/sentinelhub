// Mini bibliothèque de graphiques en SVG natif : aucune dépendance.
const NS = 'http://www.w3.org/2000/svg';

function el(name, attrs = {}, parent = null) {
    const node = document.createElementNS(NS, name);
    for (const [k, v] of Object.entries(attrs)) node.setAttribute(k, v);
    parent?.appendChild(node);
    return node;
}

function niceTicks(min, max, count = 4) {
    if (min === max) { min -= 1; max += 1; }
    const raw = (max - min) / count;
    const mag = 10 ** Math.floor(Math.log10(raw));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw);
    const start = Math.floor(min / step) * step;
    const ticks = [];
    // on s'arrête au premier palier qui couvre le maximum
    for (let v = start; ; v += step) {
        ticks.push(+v.toFixed(6));
        if (v >= max - 1e-9) break;
    }
    return ticks;
}

const fmtTime = new Intl.DateTimeFormat('fr', { hour: '2-digit', minute: '2-digit' });
const fmtDay = new Intl.DateTimeFormat('fr', { weekday: 'short', hour: '2-digit' });

/**
 * Courbe moyenne + bande min/max, avec infobulle au survol (souris et tactile).
 * points : [{t, avg, min, max}]
 */
export function lineChart(container, points, { unit = '', hours = 24 } = {}) {
    container.replaceChildren();
    if (!points.length) {
        container.innerHTML = '<p class="muted empty">Pas de données sur cette période.</p>';
        return;
    }

    const W = Math.max(container.clientWidth, 300);
    const H = W < 520 ? 220 : 280;
    const pad = { t: 12, r: 12, b: 26, l: 42 };
    const svg = el('svg', { viewBox: `0 0 ${W} ${H}`, role: 'img' }, container);

    const defs = el('defs', {}, svg);
    const grad = el('linearGradient', { id: 'fade', x1: 0, x2: 0, y1: 0, y2: 1 }, defs);
    el('stop', { offset: '0%', 'stop-color': 'var(--accent)', 'stop-opacity': .28 }, grad);
    el('stop', { offset: '100%', 'stop-color': 'var(--accent)', 'stop-opacity': 0 }, grad);

    const times = points.map((p) => new Date(p.t).getTime());
    const t0 = times[0];
    const t1 = times[times.length - 1] || t0 + 1;
    const lo = Math.min(...points.map((p) => p.min));
    const hi = Math.max(...points.map((p) => p.max));
    const ticks = niceTicks(lo, hi);
    const yMin = ticks[0];
    const yMax = ticks[ticks.length - 1];

    const x = (t) => pad.l + ((t - t0) / (t1 - t0 || 1)) * (W - pad.l - pad.r);
    const y = (v) => pad.t + (1 - (v - yMin) / (yMax - yMin || 1)) * (H - pad.t - pad.b);

    const grid = el('g', { class: 'grid' }, svg);
    const axis = el('g', { class: 'axis' }, svg);
    ticks.forEach((v) => {
        el('line', { x1: pad.l, x2: W - pad.r, y1: y(v), y2: y(v) }, grid);
        el('text', { x: pad.l - 8, y: y(v) + 3, 'text-anchor': 'end' }, axis).textContent = v;
    });

    const xTicks = W < 520 ? 3 : 6;
    for (let i = 0; i <= xTicks; i++) {
        const t = t0 + ((t1 - t0) * i) / xTicks;
        const label = (hours > 48 ? fmtDay : fmtTime).format(t);
        el('text', { x: x(t), y: H - 6, 'text-anchor': i === 0 ? 'start' : i === xTicks ? 'end' : 'middle' }, axis).textContent = label;
    }

    const band = points.map((p, i) => `${x(times[i])},${y(p.max)}`).join(' ')
        + ' ' + points.map((p, i) => `${x(times[i])},${y(p.min)}`).reverse().join(' ');
    el('polygon', { class: 'band', points: band }, svg);

    const line = points.map((p, i) => `${i ? 'L' : 'M'}${x(times[i]).toFixed(1)},${y(p.avg).toFixed(1)}`).join('');
    el('path', { class: 'area', d: `${line}L${x(t1)},${H - pad.b}L${x(t0)},${H - pad.b}Z` }, svg);
    const path = el('path', { class: 'line', d: line }, svg);

    // Animation de tracé
    const len = path.getTotalLength?.() || 0;
    if (len && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        path.style.strokeDasharray = len;
        path.style.strokeDashoffset = len;
        path.getBoundingClientRect();
        path.style.transition = 'stroke-dashoffset .9s ease';
        path.style.strokeDashoffset = 0;
    }

    // Infobulle
    const cursor = el('line', { class: 'cursor', y1: pad.t, y2: H - pad.b, visibility: 'hidden' }, svg);
    const dot = el('circle', { class: 'cursor-dot', r: 4.5, visibility: 'hidden' }, svg);
    const tip = document.createElement('div');
    tip.className = 'tooltip';
    tip.hidden = true;
    container.appendChild(tip);

    const onMove = (evt) => {
        const rect = svg.getBoundingClientRect();
        const px = ((evt.clientX - rect.left) / rect.width) * W;
        let best = 0;
        times.forEach((t, i) => { if (Math.abs(x(t) - px) < Math.abs(x(times[best]) - px)) best = i; });
        const p = points[best];
        const cx = x(times[best]);
        const cy = y(p.avg);
        cursor.setAttribute('x1', cx); cursor.setAttribute('x2', cx);
        dot.setAttribute('cx', cx); dot.setAttribute('cy', cy);
        cursor.setAttribute('visibility', 'visible'); dot.setAttribute('visibility', 'visible');
        tip.hidden = false;
        tip.innerHTML = `<b>${p.avg} ${unit}</b><br>${fmtDay.format(times[best]).replace(/ h$/, '')} ${fmtTime.format(times[best])}<br><span class="muted">min ${p.min} · max ${p.max}</span>`;
        tip.style.left = `${(cx / W) * rect.width}px`;
        tip.style.top = `${(cy / H) * rect.height}px`;
    };
    const onLeave = () => {
        tip.hidden = true;
        cursor.setAttribute('visibility', 'hidden');
        dot.setAttribute('visibility', 'hidden');
    };
    svg.addEventListener('pointermove', onMove);
    svg.addEventListener('pointerdown', onMove);
    svg.addEventListener('pointerleave', onLeave);
}

/**
 * Histogramme empilé par sévérité. rows : [{hour, info, warning, critical}]
 */
export function stackedBars(container, rows) {
    container.replaceChildren();
    const W = Math.max(container.clientWidth, 280);
    const H = W < 520 ? 170 : 200;
    const pad = { t: 8, r: 4, b: 22, l: 26 };
    const svg = el('svg', { viewBox: `0 0 ${W} ${H}` }, container);

    const totals = rows.map((r) => r.info + r.warning + r.critical);
    const ticks = niceTicks(0, Math.max(4, ...totals), 3);
    const yMax = ticks[ticks.length - 1];
    const slot = (W - pad.l - pad.r) / rows.length;
    const bw = Math.max(2, slot * 0.62);
    const y = (v) => pad.t + (1 - v / yMax) * (H - pad.t - pad.b);

    const grid = el('g', { class: 'grid' }, svg);
    const axis = el('g', { class: 'axis' }, svg);
    ticks.forEach((v) => {
        el('line', { x1: pad.l, x2: W - pad.r, y1: y(v), y2: y(v) }, grid);
        el('text', { x: pad.l - 6, y: y(v) + 3, 'text-anchor': 'end' }, axis).textContent = v;
    });

    const every = W < 520 ? 6 : 3;
    rows.forEach((r, i) => {
        const cx = pad.l + slot * i + (slot - bw) / 2;
        let base = 0;
        for (const sev of ['info', 'warning', 'critical']) {
            if (!r[sev]) continue;
            const rect = el('rect', {
                class: `bar-${sev}`, x: cx, width: bw, rx: 2,
                y: y(base + r[sev]), height: y(base) - y(base + r[sev]),
            }, svg);
            el('title', {}, rect).textContent = `${r.hour.slice(11)}h · ${r[sev]} ${sev}`;
            base += r[sev];
        }
        if (i % every === 0) {
            el('text', { x: cx + bw / 2, y: H - 6, 'text-anchor': 'middle' }, axis).textContent = `${r.hour.slice(11)}h`;
        }
    });
}
