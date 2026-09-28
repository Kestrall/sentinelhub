// Rafraîchissement du tableau de bord : un appel JSON toutes les 5 s,
// mis en pause quand l'onglet n'est pas visible.
import { stackedBars } from './charts.js';
import { refreshRelativeTimes, relative } from './time.js';

const UNITS = { temperature: '°C', humidity: '%', rssi: 'dBm', heap: 'Ko', battery: '%' };
const ICONS = { temperature: 'thermo', humidity: 'pulse', rssi: 'wifi', heap: 'chip', battery: 'pulse' };
const SHOWN = ['temperature', 'humidity', 'rssi', 'battery'];

const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

function metricHtml(key, value) {
    const digits = ['temperature', 'humidity'].includes(key) ? 1 : 0;
    const v = Number(value).toLocaleString('fr-FR', { minimumFractionDigits: digits, maximumFractionDigits: digits });
    return `<span class="metric" data-metric="${key}"><svg class="icon" aria-hidden="true"><use href="#i-${ICONS[key]}"/></svg><b>${v}</b><small>${UNITS[key]}</small></span>`;
}

function setText(root, selector, value) {
    const node = root.querySelector(selector);
    if (node && node.textContent !== String(value)) {
        node.textContent = value;
        node.classList.remove('flash-in');
        void node.offsetWidth;
        node.classList.add('flash-in');
    }
}

export function initDashboard(root) {
    const endpoint = root.dataset.endpoint;
    const histogram = root.querySelector('[data-histogram]');
    const feed = root.querySelector('[data-feed]');
    const updated = root.querySelector('[data-updated]');
    let lastData = null;
    const seenEvents = new Set();
    let firstRender = true;
    let timer = null;

    async function tick() {
        try {
            const res = await fetch(endpoint, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();
            lastData = data;
            render(data);
            updated.textContent = 'mis à jour ' + relative(data.serverTime);
        } catch {
            updated.textContent = 'connexion perdue, nouvelle tentative…';
        }
    }

    function render(data) {
        const online = data.devices.filter((d) => d.online).length;
        setText(root, '[data-kpi="online"]', online);
        setText(root, '[data-kpi="total"]', data.devices.length);
        setText(root, '[data-kpi="armed"]', data.devices.filter((d) => d.armed).length);
        setText(root, '[data-kpi="critical"]', data.alerts.critical ?? 0);
        setText(root, '[data-kpi="warning"]', data.alerts.warning ?? 0);
        setText(root, '[data-kpi="events"]', data.histogram.reduce((s, h) => s + h.info + h.warning + h.critical, 0));
        root.querySelector('[data-kpi-gauge]')?.style.setProperty('--p', `${data.devices.length ? Math.round((online / data.devices.length) * 100) : 0}%`);
        updateBadge((data.alerts.critical ?? 0) + (data.alerts.warning ?? 0) + (data.alerts.info ?? 0));

        for (const d of data.devices) {
            const card = root.querySelector(`[data-device="${d.id}"]`);
            if (!card) continue;
            card.classList.toggle('is-offline', !d.online);
            const pill = card.querySelector('[data-status]');
            pill.className = `pill pill--${d.online ? 'online' : d.lastSeen ? 'offline' : 'never'}`;
            pill.innerHTML = `<span class="dot"></span>${d.online ? 'En ligne' : d.lastSeen ? 'Hors ligne' : 'Jamais vu'}`;
            if (d.metrics) {
                card.querySelector('.device-card__metrics').innerHTML = SHOWN
                    .filter((k) => d.metrics[k] !== undefined)
                    .map((k) => metricHtml(k, d.metrics[k]))
                    .join('');
            }
            const time = card.querySelector('footer time');
            if (time && d.lastSeen) time.setAttribute('datetime', d.lastSeen);
        }

        renderFeed(data.events);
        stackedBars(histogram, data.histogram);
        refreshRelativeTimes(root);
    }

    function renderFeed(events) {
        if (!events.length) return;
        feed.innerHTML = events.map((e) => {
            const isNew = !firstRender && !seenEvents.has(e.id);
            seenEvents.add(e.id);
            return `
            <li class="feed__item feed__item--${esc(e.severity)}${isNew ? ' is-new' : ''}">
                <span class="feed__dot"></span>
                <div><b>${esc(e.deviceName)}</b> <span class="muted">·</span> ${esc(e.message)}<br>
                <small class="muted">${esc(e.typeLabel)} · <time datetime="${esc(e.occurredAt)}" data-relative></time></small></div>
            </li>`;
        }).join('');
        firstRender = false;
    }

    function schedule() {
        clearInterval(timer);
        if (document.visibilityState === 'visible') {
            tick();
            timer = setInterval(tick, 5000);
        }
    }

    document.addEventListener('visibilitychange', schedule);
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => lastData && stackedBars(histogram, lastData.histogram), 150);
    });
    schedule();
}

export function updateBadge(count) {
    const badge = document.querySelector('[data-alert-badge]');
    if (!badge) return;
    badge.hidden = !count;
    badge.textContent = count > 99 ? '99+' : count;
}
