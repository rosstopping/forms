import { test } from 'node:test';
import assert from 'node:assert/strict';
import { bootMarketingConsent, CONSENT_KEY, DENIED, queueEnquiryConversion } from '../../resources/js/marketing-consent.js';

class Storage {
    constructor(entries = []) {
        this.values = new Map(entries);
    }

    get length() { return this.values.size; }
    getItem(key) { return this.values.get(key) ?? null; }
    key(index) { return [...this.values.keys()][index] ?? null; }
    removeItem(key) { this.values.delete(key); }
    setItem(key, value) { this.values.set(key, String(value)); }
}

function element() {
    return {
        handlers: {},
        focused: false,
        addEventListener(name, handler) { this.handlers[name] = handler; },
        click() { this.handlers.click?.(); },
        focus() { this.focused = true; },
    };
}

function browser(choice = null) {
    const banner = { hidden: true };
    const accept = element();
    const reject = element();
    const manage = element();
    const scripts = [];
    const cookies = new Map([['_ga', 'GA1.1.123.456']]);
    const localStorage = new Storage();
    const sessionStorage = new Storage([['sitewell:event:old', '1']]);
    const target = {
        dataLayer: [],
        localStorage,
        sessionStorage,
        location: {
            hostname: 'sitewell.example',
            pathname: '/contact',
            reloads: 0,
            reload() { this.reloads += 1; },
        },
        handlers: {},
        addEventListener(name, handler) { this.handlers[name] = handler; },
    };
    const document = {
        head: { appendChild(script) { scripts.push(script); } },
        createElement() { return { dataset: {} }; },
        querySelector(selector) {
            return {
                '[data-consent-banner]': banner,
                '[data-consent-accept]': accept,
                '[data-consent-reject]': reject,
            }[selector] ?? null;
        },
        querySelectorAll(selector) { return selector === '[data-consent-manage]' ? [manage] : []; },
        get cookie() { return [...cookies].map(([name, value]) => `${name}=${value}`).join('; '); },
        set cookie(value) {
            const [pair, ...attributes] = value.split(';').map((part) => part.trim());
            const separator = pair.indexOf('=');
            const name = pair.slice(0, separator);
            const cookieValue = pair.slice(separator + 1);
            if (attributes.some((attribute) => attribute.toLowerCase() === 'max-age=0')) cookies.delete(name);
            else cookies.set(name, cookieValue);
        },
    };

    if (choice) {
        localStorage.setItem(CONSENT_KEY, JSON.stringify({ choice, expires: Date.now() + 60_000 }));
    }

    return { accept, banner, cookies, document, manage, reject, scripts, target };
}

function commands(target) {
    return target.dataLayer.filter((entry) => Object.prototype.toString.call(entry) === '[object Arguments]').map((entry) => [...entry]);
}

test('defaults every optional consent signal to denied and does not load GTM', () => {
    const page = browser();
    const consent = bootMarketingConsent(page.document, page.target);

    assert.equal(consent.choice, null);
    assert.equal(page.banner.hidden, false);
    assert.equal(page.scripts.length, 0);
    assert.deepEqual(commands(page.target), [['consent', 'default', DENIED]]);
});

test('rejecting persists the choice without loading GTM or queuing an enquiry conversion', () => {
    const page = browser();
    const consent = bootMarketingConsent(page.document, page.target);
    queueEnquiryConversion({ event: 'enquiry_submitted', event_id: 'saved-one', is_conversion: true }, page.target);
    page.reject.click();

    assert.equal(consent.choice, 'rejected');
    assert.equal(JSON.parse(page.target.localStorage.getItem(CONSENT_KEY)).choice, 'rejected');
    assert.equal(page.scripts.length, 0);
    assert.equal(page.target.dataLayer.some((entry) => entry?.event === 'sitewell_enquiry_submitted'), false);
    assert.deepEqual(commands(page.target).at(-1), ['consent', 'update', DENIED]);
});

test('accepting updates consent before loading GTM and releases only the saved enquiry event', () => {
    const page = browser();
    bootMarketingConsent(page.document, page.target);
    queueEnquiryConversion({ event: 'audit_submitted', event_id: 'audit-one', is_conversion: true }, page.target);
    queueEnquiryConversion({ event: 'enquiry_submitted', event_id: '', is_conversion: true }, page.target);
    queueEnquiryConversion({ event: 'enquiry_submitted', event_id: 'saved-one', is_conversion: true, email: 'private@example.test' }, page.target);
    page.accept.click();

    const consentUpdate = page.target.dataLayer.findIndex((entry) => Object.prototype.toString.call(entry) === '[object Arguments]' && entry[0] === 'consent' && entry[1] === 'update');
    const gtmStart = page.target.dataLayer.findIndex((entry) => entry?.event === 'gtm.js');
    assert.ok(consentUpdate >= 0 && consentUpdate < gtmStart);
    assert.equal(page.scripts.length, 1);
    assert.equal(page.scripts[0].src, 'https://www.googletagmanager.com/gtm.js?id=GTM-N36VDJNH');
    assert.deepEqual(page.target.dataLayer.filter((entry) => entry?.event === 'sitewell_enquiry_submitted'), [{ event: 'sitewell_enquiry_submitted', event_id: 'saved-one', transaction_id: 'saved-one', conversion_stage: 'enquiry_saved' }]);
    assert.equal(JSON.stringify(page.target.dataLayer).includes('private@example.test'), false);
});

test('a returning accepted visitor loads GTM without showing the banner', () => {
    const page = browser('accepted');
    const consent = bootMarketingConsent(page.document, page.target);

    assert.equal(consent.choice, 'accepted');
    assert.equal(page.banner.hidden, true);
    assert.equal(page.scripts.length, 1);
    assert.equal(commands(page.target).some((entry) => entry[0] === 'consent' && entry[1] === 'update' && entry[2].analytics_storage === 'granted'), true);
});

test('cookie settings allows an accepted visitor to withdraw, clear measurement and reload', () => {
    const page = browser('accepted');
    const consent = bootMarketingConsent(page.document, page.target);
    page.manage.click();

    assert.equal(page.banner.hidden, false);
    assert.equal(page.accept.focused, true);
    consent.choose('rejected');

    assert.equal(consent.choice, 'rejected');
    assert.equal(page.cookies.has('_ga'), false);
    assert.equal(page.target.sessionStorage.getItem('sitewell:event:old'), null);
    assert.equal(page.target.location.reloads, 1);
    assert.deepEqual(commands(page.target), [['consent', 'default', DENIED]]);
});
