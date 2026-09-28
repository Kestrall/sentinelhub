// Dates relatives en français (« il y a 3 min »), rafraîchies chaque minute.
const rtf = new Intl.RelativeTimeFormat('fr', { numeric: 'auto', style: 'short' });
const UNITS = [
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
    ['second', 1],
];

export function relative(date) {
    const diff = (new Date(date).getTime() - Date.now()) / 1000;
    if (Math.abs(diff) < 10) return 'à l’instant';
    for (const [unit, secs] of UNITS) {
        if (Math.abs(diff) >= secs || unit === 'second') {
            return rtf.format(Math.round(diff / secs), unit);
        }
    }
    return '';
}

export function refreshRelativeTimes(root = document) {
    root.querySelectorAll('time[data-relative]').forEach((el) => {
        el.textContent = relative(el.getAttribute('datetime'));
    });
}

export function startRelativeClock() {
    refreshRelativeTimes();
    setInterval(refreshRelativeTimes, 30_000);
}
