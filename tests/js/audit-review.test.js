import { test } from 'node:test';
import assert from 'node:assert/strict';
import { bootAuditReview } from '../../resources/js/audit-review.js';

function fixture(storage = new Map()) {
    const callbacks = {};
    const buttons = {};
    const dialog = {
        dataset: { auditId: 'audit-one', hasErrors: 'false' }, open: false, opens: 0,
        showModal() { this.open = true; this.opens++; },
        close() { this.open = false; callbacks.close?.(); },
        addEventListener(name, fn) { callbacks[name] = fn; },
        querySelector: () => ({ addEventListener(name, fn) { buttons.close = fn; } }),
    };
    const target = {
        scrollY: 0, innerHeight: 800,
        sessionStorage: { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value) },
        addEventListener(name, fn) { callbacks[name] = fn; },
        removeEventListener(name) { delete callbacks[name]; },
    };
    const end = { top: 1000, getBoundingClientRect() { return { top: this.top }; } };
    const document = {
        visibilityState: 'visible',
        querySelector: selector => selector === '[data-audit-review-prompt]' ? dialog : selector === '[data-audit-snapshot-end]' ? end : dialog.open ? dialog : null,
        querySelectorAll: selector => [{ addEventListener(name, fn) { buttons[selector] = fn; } }],
    };
    return { dialog, target, document, end, buttons, scroll: () => callbacks.scroll?.() };
}

test('opens only after scrolling to the snapshot end and never repeats after dismissal or reload', () => {
    const storage = new Map();
    const f = fixture(storage);
    bootAuditReview(f.document, f.target);
    f.end.top = 500;
    f.scroll();
    assert.equal(f.dialog.opens, 0);
    f.target.scrollY = 100;
    f.end.top = 900;
    f.scroll();
    assert.equal(f.dialog.opens, 0);
    f.end.top = 500;
    f.scroll();
    assert.equal(f.dialog.opens, 1);
    f.dialog.close();
    f.scroll();
    assert.equal(f.dialog.opens, 1);
    const reload = fixture(storage);
    bootAuditReview(reload.document, reload.target);
    reload.target.scrollY = 100;
    reload.end.top = 100;
    reload.scroll();
    assert.equal(reload.dialog.opens, 0);
    reload.buttons['[data-audit-email-open]']();
    assert.equal(reload.dialog.opens, 1);
});

test('choosing the calendar prevents an automatic review interruption', () => {
    const f = fixture();
    bootAuditReview(f.document, f.target);
    f.buttons['[data-audit-book-call]']();
    f.target.scrollY = 100;
    f.end.top = 100;
    f.scroll();
    assert.equal(f.dialog.opens, 0);
});

test('does not prompt in a hidden tab or over an open dialog', () => {
    const f = fixture();
    bootAuditReview(f.document, f.target);
    f.target.scrollY = 100;
    f.end.top = 100;
    f.document.visibilityState = 'hidden';
    f.scroll();
    assert.equal(f.dialog.opens, 0);
    f.document.visibilityState = 'visible';
    f.dialog.open = true;
    f.scroll();
    assert.equal(f.dialog.opens, 0);
});

test('validation errors reopen the form even after a previous dismissal', () => {
    const f = fixture(new Map([['sitewell-audit-email-dismissed:audit-one', '1']]));
    f.dialog.dataset.hasErrors = 'true';
    bootAuditReview(f.document, f.target);
    assert.equal(f.dialog.opens, 1);
});

test('storage restrictions still allow one prompt per page', () => {
    const f = fixture();
    f.target.sessionStorage = { getItem() { throw Error('blocked'); }, setItem() { throw Error('blocked'); } };
    bootAuditReview(f.document, f.target);
    f.target.scrollY = 100;
    f.end.top = 100;
    f.scroll();
    f.dialog.close();
    f.scroll();
    assert.equal(f.dialog.opens, 1);
});
