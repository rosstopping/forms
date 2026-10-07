export function emitAuditEngagement(document, event) {
    document.dispatchEvent?.(new CustomEvent('sitewell:audit-engagement', { detail: event }));
}

export function bootAuditEngagement(document, target = window, clock = () => Date.now()) {
    const page = document.querySelector('[data-audit-engagement-url]');
    if (!page?.dataset.auditEngagementUrl || !target.crypto?.randomUUID) return;

    const visitId = target.crypto.randomUUID();
    const visitInput = document.querySelector('[name="engagement_visit_id"]');
    if (visitInput) visitInput.value = visitId;
    const pending = new Set(['viewed']);
    let activeMilliseconds = 0;
    let lastTick = clock();
    let lastActivity = lastTick;
    let scrollPercent = 0;
    let sending = false;
    const tick = () => {
        const now = clock();
        if (document.visibilityState === 'visible' && (document.hasFocus?.() ?? true) && now - lastActivity < 30000) {
            activeMilliseconds += Math.min(Math.max(0, now - lastTick), 5000);
        }
        lastTick = now;
    };
    const flush = async (leaving = false) => {
        tick();
        if (sending && !leaving) return;
        const events = [...pending];
        pending.clear();
        sending = true;
        try {
            const response = await target.fetch(page.dataset.auditEngagementUrl, {
                method: 'POST', credentials: 'same-origin', keepalive: true,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    _token: page.dataset.csrfToken, visit_id: visitId,
                    events: events.length ? events : ['heartbeat'],
                    active_seconds: Math.min(7200, Math.floor(activeMilliseconds / 1000)),
                    scroll_percent: scrollPercent,
                }),
            });
            if (response.status !== 204) events.forEach(event => pending.add(event));
        } catch {
            events.forEach(event => pending.add(event));
        } finally {
            sending = false;
        }
    };
    const activity = () => { lastActivity = clock(); };
    ['pointerdown', 'pointermove', 'keydown', 'touchstart'].forEach(event => document.addEventListener(event, activity, { passive: true }));
    target.addEventListener('scroll', () => {
        activity();
        const height = document.documentElement.scrollHeight - target.innerHeight;
        scrollPercent = Math.max(scrollPercent, height > 0 ? Math.min(100, Math.round(target.scrollY / height * 100)) : 100);
    }, { passive: true });
    document.addEventListener('sitewell:audit-engagement', event => {
        pending.add(event.detail);
        void flush();
    });
    const email = document.querySelector('#audit-report-email');
    let emailStarted = false;
    email?.addEventListener('input', () => {
        activity();
        if (!emailStarted && email.value.trim()) {
            emailStarted = true;
            emitAuditEngagement(document, 'email_started');
        }
    });
    email?.form?.addEventListener('submit', () => emitAuditEngagement(document, 'email_submit_attempted'));
    document.addEventListener('visibilitychange', () => {
        tick();
        if (document.visibilityState === 'hidden') void flush(true);
    });
    const tickTimer = target.setInterval(tick, 5000);
    const flushTimer = target.setInterval(flush, 15000);
    target.addEventListener('pagehide', () => {
        target.clearInterval(tickTimer);
        target.clearInterval(flushTimer);
        void flush(true);
    });
    void flush();
}
