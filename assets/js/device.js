// Page appareil : onglets de métriques + période, graphique rechargé à la demande.
import { lineChart } from './charts.js';

export function initSeries(root) {
    const chart = root.querySelector('[data-chart]');
    const endpoint = root.dataset.endpoint;
    const tabs = [...root.querySelectorAll('[data-metric-tab]')];
    const ranges = [...root.querySelectorAll('[data-range]')];
    const state = { metric: tabs[0]?.dataset.metricTab ?? 'temperature', hours: 24, data: null };
    let controller = null;

    async function load() {
        controller?.abort();
        controller = new AbortController();
        chart.setAttribute('aria-busy', 'true');
        try {
            const url = `${endpoint}?metric=${state.metric}&hours=${state.hours}`;
            const res = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
            state.data = await res.json();
            draw();
        } catch (e) {
            if (e.name !== 'AbortError') chart.innerHTML = '<p class="muted empty">Impossible de charger la série.</p>';
        } finally {
            chart.removeAttribute('aria-busy');
        }
    }

    const draw = () => state.data && lineChart(chart, state.data.points, { unit: state.data.unit, hours: state.data.hours });

    tabs.forEach((tab) => tab.addEventListener('click', () => {
        tabs.forEach((t) => t.setAttribute('aria-selected', String(t === tab)));
        state.metric = tab.dataset.metricTab;
        load();
    }));
    ranges.forEach((btn) => btn.addEventListener('click', () => {
        ranges.forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
        state.hours = Number(btn.dataset.range);
        load();
    }));

    let resizeTimer;
    window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(draw, 150); });
    setInterval(() => document.visibilityState === 'visible' && load(), 30_000);
    load();
}
