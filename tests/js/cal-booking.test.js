import { test } from 'node:test';
import assert from 'node:assert/strict';
import { bootCalBooking } from '../../resources/js/cal-booking.js';

function fixture() {
    let click;
    const link = { href: 'https://sitewell.test/book', setAttribute() {}, removeAttribute() {}, addEventListener(name, callback) { click = callback; } };
    const calls = [];
    const navigations = [];
    const target = {
        Cal: (...args) => calls.push(args),
        fetch: async () => ({ ok: true, json: async () => ({ booking_url: 'https://cal.com/ross/intro?metadata%5Bsitewell_booking%5D=token' }) }),
        location: { assign: url => navigations.push(url) },
    };
    const event = { button: 0, preventDefault() { this.prevented = true; } };
    return { link, target, calls, navigations, event, document: { querySelectorAll: () => [link] }, click: () => click(event) };
}

test('opens a Cal popup with the attributed booking URL and keeps the report open', async () => {
    const f = fixture();
    bootCalBooking(f.document, f.target, async () => {});
    await f.click();
    assert.equal(f.event.prevented, true);
    assert.deepEqual(f.calls, [['init', { origin: 'https://cal.com' }], ['modal', { calLink: 'ross/intro?metadata%5Bsitewell_booking%5D=token' }]]);
    assert.deepEqual(f.navigations, []);
});

test('uses the existing booking route if the embed cannot load', async () => {
    const f = fixture();
    bootCalBooking(f.document, f.target, async () => { throw new Error('blocked'); });
    await f.click();
    assert.deepEqual(f.navigations, [f.link.href]);
});

test('preserves modified clicks as normal links', async () => {
    const f = fixture();
    f.event.ctrlKey = true;
    bootCalBooking(f.document, f.target, async () => {});
    await f.click();
    assert.equal(f.event.prevented, undefined);
    assert.deepEqual(f.calls, []);
});

test('ignores repeated clicks while the calendar is loading', async () => {
    const f = fixture();
    let ready;
    bootCalBooking(f.document, f.target, () => new Promise(resolve => { ready = resolve; }));
    const first = f.click();
    await f.click();
    ready();
    await first;
    assert.equal(f.calls.length, 2);
});
