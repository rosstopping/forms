import { test } from 'node:test';
import assert from 'node:assert/strict';
import { initializeOutreachDraftEditors } from '../../resources/js/outreach-draft-editor.js';

function field(value = '') {
    return {
        value, listeners: {}, events: [], disabled: true,
        addEventListener(type, callback) { this.listeners[type] = callback; },
        dispatchEvent(event) { this.events.push(event.type); },
        focus() { this.focused = true; },
    };
}

test('template selection waits for apply and changes only the editable copy', () => {
    const select = field();
    const apply = field();
    const subject = field('Original subject');
    const body = field('Original message');
    const video = field('https://video.example/saved');
    const status = {};
    const controls = {
        '[data-outreach-template]': select,
        '[data-outreach-template-apply]': apply,
        '[data-outreach-template-status]': status,
        '[name="outreach_subject"]': subject,
        '[name="outreach_body"]': body,
        '[name="showcase_video_url"]': video,
    };
    initializeOutreachDraftEditors({ querySelectorAll: () => [{ querySelector: selector => controls[selector] }] });
    select.value = 'personalised_video';
    select.selectedOptions = [{ value: select.value, textContent: 'Personalised video', dataset: {
        subject: 'A video for Acme', body: 'Hi Alex,\n\nA video & introduction <for you>.',
    } }];
    select.listeners.change();
    assert.equal(apply.disabled, false);
    assert.equal(body.value, 'Original message');
    apply.listeners.click();
    assert.equal(subject.value, 'A video for Acme');
    assert.equal(body.value, 'Hi Alex,\n\nA video & introduction <for you>.');
    assert.equal(video.value, 'https://video.example/saved');
    assert.equal(subject.focused, true);
    assert.deepEqual(body.events, ['input']);
    assert.match(status.textContent, /save the draft/);
    select.value = '';
    select.listeners.change();
    assert.equal(apply.disabled, true);
});

test('pages without a draft editor need no controls', () => {
    assert.doesNotThrow(() => initializeOutreachDraftEditors({ querySelectorAll: () => [] }));
});
