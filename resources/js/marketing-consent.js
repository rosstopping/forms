export const CONSENT_KEY = 'sitewell:measurement-consent:v1';
const COOKIE = 'sitewell_measurement_consent';
const MAX_AGE = 180 * 24 * 60 * 60 * 1000;
export const DENIED = { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied' };
const GRANTED = Object.fromEntries(Object.keys(DENIED).map((key) => [key, 'granted']));
const measurementName = /^(?:_ga(?:_|$)|_gid$|_gat|_gac_|_gcl|__gads$|__gpi$|__eoi$|FPAU$|FPGCL|FPLC$|FPID$)/;

function command(target, ...args) {
    target.dataLayer ??= [];
    if (typeof target.gtag === 'function') {
        target.gtag(...args);
        return;
    }

    (function () {
        target.dataLayer.push(arguments);
    })(...args);
}

function storedChoice(document, target) {
    let raw;
    try { raw = target.localStorage.getItem(CONSENT_KEY); } catch {}
    raw ??= (document.cookie ?? '').split('; ').find((entry) => entry.startsWith(`${COOKIE}=`))?.slice(COOKIE.length + 1);
    try {
        const value = JSON.parse(decodeURIComponent(raw));
        if (['accepted', 'rejected'].includes(value.choice) && Number.isFinite(value.expires) && value.expires > Date.now() && value.expires <= Date.now() + MAX_AGE + 60000) return value.choice;
    } catch {}
    return null;
}

function clearMeasurement(document, target) {
    const names = (document.cookie ?? '').split(';').map((entry) => entry.trim().split('=')[0]).filter((name) => measurementName.test(name));
    const host = target.location.hostname;
    const domains = ['', host, `.${host}`];
    const parts = host.split('.');
    while (parts.length > 2) { parts.shift(); domains.push(parts.join('.'), `.${parts.join('.')}`); }
    const paths = new Set(['/']);
    let path = '';
    for (const part of target.location.pathname.split('/').filter(Boolean)) { path += `/${part}`; paths.add(path); paths.add(`${path}/`); }
    for (const name of names) for (const domain of domains) for (const path of paths) {
        document.cookie = `${name}=; Max-Age=0; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=${path};${domain ? ` domain=${domain};` : ''} SameSite=Lax; Secure`;
    }
    for (const storage of [target.localStorage, target.sessionStorage]) {
        try {
            const keys = Array.from({ length: storage.length }, (_, index) => storage.key(index));
            for (const key of keys) if (key && (measurementName.test(key) || key.startsWith('sitewell:event:') || key.startsWith('sitewell:conversion:'))) storage.removeItem(key);
        } catch {}
    }
}

function emitEnquiry(eventId, target) {
    target.sitewellSentConversions ??= new Set();
    const key = `sitewell:conversion:${eventId}`;
    if (target.sitewellSentConversions.has(eventId)) return;
    try {
        if (target.sessionStorage.getItem(key)) return;
        target.sessionStorage.setItem(key, '1');
    } catch {}
    target.sitewellSentConversions.add(eventId);
    target.dataLayer ??= [];
    target.dataLayer.push({ event: 'sitewell_enquiry_submitted', event_id: eventId, transaction_id: eventId, conversion_stage: 'enquiry_saved' });
}

export function queueEnquiryConversion(payload, target = window) {
    if (payload?.event !== 'enquiry_submitted' || payload.is_conversion !== true || typeof payload.event_id !== 'string' || !payload.event_id.trim()) return;
    if (target.sitewellConsent?.choice === 'accepted') emitEnquiry(payload.event_id, target);
    else if (target.sitewellConsent?.choice !== 'rejected') {
        target.sitewellPendingEnquiries ??= new Set();
        target.sitewellPendingEnquiries.add(payload.event_id);
    }
}

export function bootMarketingConsent(document, target = window) {
    if (target.sitewellConsent?.initialized) return target.sitewellConsent;
    if (!target.sitewellConsentDefaultsSet) {
        command(target, 'consent', 'default', { ...DENIED });
        target.sitewellConsentDefaultsSet = true;
    }
    const banner = document.querySelector('[data-consent-banner]');
    let loaded = false;
    let returnFocus;
    const controller = target.sitewellConsent = {
        initialized: true, choice: storedChoice(document, target),
        show(trigger) {
            returnFocus = trigger;
            if (banner) banner.hidden = false;
            document.querySelector('[data-consent-accept]')?.focus();
        },
        choose(choice, persist = true) {
            if (!['accepted', 'rejected'].includes(choice)) return;
            controller.choice = choice;
            if (persist) {
                const value = JSON.stringify({ choice, expires: Date.now() + MAX_AGE });
                try { target.localStorage.setItem(CONSENT_KEY, value); } catch {}
                document.cookie = `${COOKIE}=${encodeURIComponent(value)}; Max-Age=${MAX_AGE / 1000}; path=/; SameSite=Lax; Secure`;
            }
            if (banner) banner.hidden = true;
            if (choice === 'accepted') {
                // Consent update is queued before GTM and before saved-enquiry events.
                command(target, 'consent', 'update', { ...GRANTED });
                if (!loaded) {
                    loaded = true;
                    command(target, 'set', 'ads_data_redaction', true);
                    target.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
                    const script = document.createElement('script');
                    script.async = true;
                    script.dataset.sitewellGtm = 'true';
                    script.src = 'https://www.googletagmanager.com/gtm.js?id=GTM-N36VDJNH';
                    document.head.appendChild(script);
                }
                for (const id of target.sitewellPendingEnquiries ?? []) emitEnquiry(id, target);
                target.sitewellPendingEnquiries?.clear();
            } else {
                target.sitewellPendingEnquiries?.clear();
                clearMeasurement(document, target);
                if (loaded) {
                    // Reload terminates already-executing Google code; removing its script does not.
                    target.dataLayer = [];
                    command(target, 'consent', 'default', { ...DENIED });
                    target.location.reload();
                    return;
                }
                command(target, 'consent', 'update', { ...DENIED });
            }
            returnFocus?.focus();
        },
    };
    document.querySelector('[data-consent-accept]')?.addEventListener('click', () => controller.choose('accepted'));
    document.querySelector('[data-consent-reject]')?.addEventListener('click', () => controller.choose('rejected'));
    document.querySelectorAll('[data-consent-manage]').forEach((button) => button.addEventListener('click', () => controller.show(button)));
    target.addEventListener?.('storage', (event) => {
        if (event.key === CONSENT_KEY) {
            const choice = storedChoice(document, target);
            if (choice === 'rejected' || choice === null) controller.choose('rejected', false);
        }
    });
    if (controller.choice === 'accepted') controller.choose('accepted', false);
    else {
        clearMeasurement(document, target);
        if (banner) banner.hidden = controller.choice === 'rejected';
    }
    return controller;
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => bootMarketingConsent(document));
    else bootMarketingConsent(document);
}
