import './styles/app.css';
import { startRelativeClock } from './js/time.js';
import { initDashboard, updateBadge } from './js/dashboard.js';
import { initSeries } from './js/device.js';

// ----- Navigation mobile (tiroir latéral) -----
function initNav() {
    const toggle = document.querySelector('[data-nav-toggle]');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.querySelector('[data-nav-backdrop]');
    if (!toggle || !sidebar) return;

    const set = (open) => {
        sidebar.classList.toggle('is-open', open);
        backdrop.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        document.body.style.overflow = open ? 'hidden' : '';
    };
    toggle.addEventListener('click', () => set(!sidebar.classList.contains('is-open')));
    backdrop.addEventListener('click', () => set(false));
    document.addEventListener('keydown', (e) => e.key === 'Escape' && set(false));
}

// ----- Petits comportements globaux (délégation d'événements) -----
function initGlobalHandlers() {
    document.addEventListener('click', async (e) => {
        const dismiss = e.target.closest('[data-dismiss]');
        if (dismiss) dismiss.parentElement.remove();

        const copy = e.target.closest('[data-copy]');
        if (copy) {
            const source = copy.closest('.code-line, .code-block')?.querySelector('[data-copy-source]');
            try {
                await navigator.clipboard.writeText(source.textContent.trim());
                const label = copy.innerHTML;
                copy.textContent = 'Copié ✓';
                setTimeout(() => (copy.innerHTML = label), 1500);
            } catch {
                getSelection().selectAllChildren(source);
            }
        }
    });

    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            e.preventDefault();
            return;
        }

        // Acquittement d'alerte en AJAX : pas de rechargement de page.
        if (form.matches('[data-ack]')) {
            e.preventDefault();
            const item = form.closest('.alert-item');
            const res = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (res.ok) {
                item.classList.add('is-leaving');
                setTimeout(() => {
                    const list = item.parentElement;
                    item.remove();
                    if (!list.querySelector('.alert-item')) {
                        list.innerHTML = '<li class="empty">Tout est calme. Aucune alerte en attente.</li>';
                    }
                }, 300);
            }
        }
    });
}

async function initAlertBadge() {
    if (document.querySelector('[data-dashboard]') || !document.querySelector('[data-alert-badge]')) return;
    try {
        const res = await fetch('/api/live/dashboard', { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const { alerts } = await res.json();
            updateBadge(Object.values(alerts).reduce((a, b) => a + b, 0));
        }
    } catch { /* hors ligne : pas de badge */ }
}

initNav();
initGlobalHandlers();
startRelativeClock();
initAlertBadge();
document.querySelectorAll('[data-dashboard]').forEach(initDashboard);
document.querySelectorAll('[data-series]').forEach(initSeries);
