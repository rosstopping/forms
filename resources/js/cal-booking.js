import { emitAuditEngagement } from './audit-engagement.js';

let embedPromise;

function loadCalEmbed(document, target) {
    embedPromise ??= new Promise((resolve, reject) => {
        target.Cal ??= Object.assign(function (...args) { target.Cal.q.push(args); }, { q: [], ns: {} });
        const script = document.createElement('script');
        script.src = 'https://app.cal.com/embed/embed.js';
        script.async = true;
        const timeout = target.setTimeout(() => reject(new Error('Calendar load timed out')), 8000);
        script.onload = () => { target.clearTimeout(timeout); resolve(); };
        script.onerror = () => { target.clearTimeout(timeout); reject(new Error('Calendar could not load')); };
        document.head.appendChild(script);
    });
    return embedPromise;
}

export function bootCalBooking(document, target = window, loadEmbed = () => loadCalEmbed(document, target)) {
    document.querySelectorAll('[data-audit-book-call]').forEach((link) => {
        let opening = false;
        link.addEventListener('click', async (event) => {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            if (opening) return;
            opening = true;
            emitAuditEngagement(document, 'booking_clicked');
            link.setAttribute('aria-busy', 'true');
            let bookingUrl;
            try {
                await loadEmbed();
                const response = await target.fetch(link.href, {
                    headers: { Accept: 'application/json' },
                    signal: AbortSignal.timeout(10000),
                });
                if (!response.ok) throw new Error('Booking link unavailable');
                bookingUrl = (await response.json()).booking_url;
                const url = new URL(bookingUrl);
                if (url.hostname !== 'cal.com' || url.protocol !== 'https:') throw new Error('Unsupported calendar');
                target.Cal('init', { origin: url.origin });
                target.Cal('modal', { calLink: url.pathname.replace(/^\//, '') + url.search });
                emitAuditEngagement(document, 'calendar_opened');
            } catch {
                target.location.assign(bookingUrl || link.href);
            } finally {
                opening = false;
                link.removeAttribute('aria-busy');
            }
        });
    });
}
