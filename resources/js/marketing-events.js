import { bootCalBooking } from './cal-booking.js';
import { bootAuditReview } from './audit-review.js';
import { bootAuditEngagement } from './audit-engagement.js';

/**
 * Publish the existing marketing hooks and hand accepted audits to GTM.
 * The GTM conversion tag must respect consent; do not also send this lead
 * through a server-side Ads conversion or a GA4 import.
 */
export function publishMarketingEvent(payload, target = window) {
    target.sitewellEvents ??= [];
    const key = `sitewell:event:${payload.event_id}`;
    if (target.sitewellEvents.some((event) => event.event_id === payload.event_id)) return;

    try {
        if (target.sessionStorage.getItem(key)) return;
        target.sessionStorage.setItem(key, '1');
    } catch {
        // In-memory deduplication still works when browser storage is unavailable.
    }

    target.sitewellEvents.push(payload);
    if (payload.event === 'audit_submitted' && payload.is_conversion === true) {
        target.dataLayer ??= [];
        target.dataLayer.push({ event: 'sitewell_audit_submitted', event_id: payload.event_id });
    }
    target.dispatchEvent(new CustomEvent('sitewell:marketing-event', { detail: payload }));
}

export function bootMarketingEvents(document, target = window) {
    bootAuditEngagement(document, target);
    bootCalBooking(document, target);
    bootAuditReview(document, target);
    const page = document.querySelector('[data-ppc-page]');
    if (page) {
        publishMarketingEvent({
            event: 'ppc_landing_page_view',
            event_id: target.crypto.randomUUID(),
            is_conversion: false,
            landing_page: page.dataset.ppcPage,
            attribution: JSON.parse(page.dataset.marketingAttribution),
        }, target);
    }

    document.querySelectorAll('[data-audit-form]').forEach((form) => {
        const start = () => {
            if (form.dataset.auditStarted) return;
            form.dataset.auditStarted = 'true';
            const attribution = JSON.parse(form.dataset.marketingAttribution);
            publishMarketingEvent({
                event: 'audit_started',
                event_id: `audit_started:${attribution.journey_id}`,
                is_conversion: false,
                attribution,
            }, target);
        };
        form.addEventListener('input', start, { once: true });
        form.addEventListener('submit', start, { once: true });
    });

    document.querySelectorAll('[data-marketing-events]').forEach((element) => {
        JSON.parse(element.dataset.marketingEvents).forEach((payload) => publishMarketingEvent(payload, target));
    });
}

if (typeof document !== 'undefined') bootMarketingEvents(document);
