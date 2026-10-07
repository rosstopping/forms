import { test } from 'node:test';
import assert from 'node:assert/strict';
import { bootAuditEngagement } from '../../resources/js/audit-engagement.js';

function fixture() {
    const listeners = {};
    const timers = {};
    const payloads = [];
    let clock = 0;
    const input = { value: '', form: { addEventListener: (event, fn) => { listeners[`form:${event}`] = fn; } }, addEventListener: (event, fn) => { listeners[`email:${event}`] = fn; } };
    const visitInput = { value: '' };
    const document = {
        visibilityState: 'visible', hasFocus: () => true, documentElement: { scrollHeight: 2000 },
        querySelector: selector => selector === '[data-audit-engagement-url]' ? { dataset: { auditEngagementUrl: '/track?signed=1', csrfToken: 'csrf' } } : selector === '#audit-report-email' ? input : visitInput,
        addEventListener: (event, fn) => { listeners[event] = fn; },
        dispatchEvent: event => listeners[event.type]?.(event),
    };
    const target = {
        crypto: { randomUUID: () => 'test-visit' }, scrollY: 0, innerHeight: 1000,
        setInterval: (fn, delay) => { timers[delay] = fn; return delay; }, clearInterval: id => { delete timers[id]; },
        addEventListener: (event, fn) => { listeners[event] = fn; },
        fetch: async (url, options) => { payloads.push({ url, ...options, data: JSON.parse(options.body) }); return { ok: true, status: 204 }; },
    };
    bootAuditEngagement(document, target, () => clock);
    return { document, target, timers, listeners, payloads, input, visitInput, advance: seconds => { clock += seconds * 1000; timers[5000]?.(); }, settle: () => new Promise(resolve => setImmediate(resolve)) };
}

test('sends first-party visit and active time while excluding hidden and idle intervals', async () => {
    const f = fixture();
    await f.settle();
    assert.equal(f.visitInput.value, 'test-visit');
    assert.deepEqual(f.payloads[0].data.events, ['viewed']);
    f.advance(5); f.advance(5);
    f.document.visibilityState = 'hidden';
    f.advance(5); f.advance(5);
    f.document.visibilityState = 'visible';
    f.advance(20);
    await f.timers[15000]();
    assert.equal(f.payloads.at(-1).data.active_seconds, 10);
    f.listeners.pointerdown();
    f.advance(5);
    f.target.scrollY = 500;
    f.listeners.scroll();
    await f.timers[15000]();
    assert.equal(f.payloads.at(-1).data.active_seconds, 15);
    assert.equal(f.payloads.at(-1).data.scroll_percent, 50);
});

test('records email typing and submission intent without sending any unfinished address', async () => {
    const f = fixture();
    await f.settle();
    f.input.value = 'private-unfinished@example.com';
    f.listeners['email:input']();
    await f.settle();
    assert.deepEqual(f.payloads.at(-1).data.events, ['email_started']);
    f.listeners['form:submit']();
    await f.settle();
    assert.deepEqual(f.payloads.at(-1).data.events, ['email_submit_attempted']);
    assert.ok(f.payloads.every(payload => !payload.body.includes(f.input.value)));
});

test('flushes final active time on page exit and stops timers', async () => {
    const f = fixture();
    await f.settle();
    f.advance(5);
    f.listeners.pagehide();
    await f.settle();
    assert.equal(f.payloads.at(-1).keepalive, true);
    assert.equal(f.payloads.at(-1).data.active_seconds, 5);
    assert.deepEqual(Object.keys(f.timers), []);
});
