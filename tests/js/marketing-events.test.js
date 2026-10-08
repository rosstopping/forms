import { test } from 'node:test';
import assert from 'node:assert/strict';
import { bootMarketingEvents, publishMarketingEvent } from '../../resources/js/marketing-events.js';

function browser(storage = new Map()) {
    return {
        crypto: { randomUUID: () => 'page-view-id' },
        sessionStorage: { getItem: (key) => storage.get(key), setItem: (key, value) => storage.set(key, value) },
        dispatched: [],
        dispatchEvent(event) { this.dispatched.push(event.detail); },
    };
}

test('deduplicates server conversions across polling reloads', () => {
    const storage = new Map();
    const first = browser(storage);
    const payload = { event: 'lead_captured', event_id: 'lead-one', is_conversion: true };
    publishMarketingEvent(payload, first);
    publishMarketingEvent(payload, first);
    const reloaded = browser(storage);
    publishMarketingEvent(payload, reloaded);
    assert.equal(first.dispatched.length, 1);
    assert.equal(reloaded.dispatched.length, 0);
    assert.deepEqual(first.dataLayer, [{ event: 'sitewell_audit_submitted', event_id: 'lead-one', conversion_stage: 'email_captured' }]);
    assert.equal(reloaded.dataLayer, undefined);
});

test('still queues hooks when session storage is unavailable', () => {
    const target = browser();
    target.sessionStorage.getItem = () => { throw new Error('blocked'); };
    publishMarketingEvent({ event: 'audit_completed', event_id: 'completed-one', is_conversion: false }, target);
    publishMarketingEvent({ event: 'audit_completed', event_id: 'completed-one', is_conversion: false }, target);
    assert.equal(target.sitewellEvents.length, 1);
});

test('page views and form interaction are separate non-conversion events', () => {
    const target = browser();
    const listeners = {};
    const attribution = JSON.stringify({ journey_id: 'journey-one', first_touch: { gclid: 'click-one' } });
    const form = { dataset: { marketingAttribution: attribution }, addEventListener(name, handler) { listeners[name] = handler; } };
    const document = {
        querySelector: () => ({ dataset: { ppcPage: 'managed', marketingAttribution: attribution } }),
        querySelectorAll: (selector) => selector === '[data-audit-form]' ? [form] : [],
    };
    bootMarketingEvents(document, target);
    assert.equal(target.sitewellEvents.length, 1);
    assert.equal(target.sitewellEvents[0].event, 'ppc_landing_page_view');
    listeners.input();
    listeners.submit();
    assert.equal(target.sitewellEvents.length, 2);
    assert.equal(target.sitewellEvents[1].event, 'audit_started');
    assert.ok(target.sitewellEvents.every((event) => event.is_conversion === false));
    assert.equal(target.dataLayer, undefined);
});

test('publishes actual server event ids without manufacturing submission on a click', () => {
    const target = browser();
    const payload = { event: 'lead_captured', event_id: 'server-event-id', is_conversion: true };
    const document = {
        querySelector: () => null,
        querySelectorAll: (selector) => selector === '[data-marketing-events]' ? [{ dataset: { marketingEvents: JSON.stringify([payload]) } }] : [],
    };
    bootMarketingEvents(document, target);
    assert.deepEqual(target.sitewellEvents, [payload]);
    assert.deepEqual(target.dataLayer, [{ event: 'sitewell_audit_submitted', event_id: 'server-event-id', conversion_stage: 'email_captured' }]);
});

test('audit submissions never fire the installed Ads conversion tag, including old payloads', () => {
    const target = browser();
    publishMarketingEvent({ event: 'audit_submitted', event_id: 'new-audit', is_conversion: false }, target);
    publishMarketingEvent({ event: 'audit_submitted', event_id: 'legacy-audit', is_conversion: true }, target);
    publishMarketingEvent({ event: 'lead_captured', event_id: 'not-a-conversion', is_conversion: false }, target);
    assert.equal(target.sitewellEvents.length, 3);
    assert.equal(target.dataLayer, undefined);
});

test('captured leads still deduplicate without browser storage', () => {
    const target = browser();
    target.sessionStorage.getItem = () => { throw new Error('blocked'); };
    const payload = { event: 'lead_captured', event_id: 'lead-one', is_conversion: true };
    publishMarketingEvent(payload, target);
    publishMarketingEvent(payload, target);
    assert.equal(target.dataLayer.length, 1);
});
