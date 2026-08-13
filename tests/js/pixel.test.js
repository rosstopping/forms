import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/pixel.js', import.meta.url), 'utf8');

class ElementDouble {
    constructor(tagName = 'DIV') {
        this.tagName = tagName.toUpperCase();
        this.attributes = new Map();
        this.childNodes = [];
        this.innerHTML = '';
        this.parentNode = null;
        this.textContent = '';
    }

    setAttribute(name, value) {
        this.attributes.set(name, value);
    }

    getAttribute(name) {
        return this.attributes.get(name) ?? null;
    }

    appendChild(child) {
        child.parentNode = this;
        this.childNodes.push(child);

        return child;
    }

    insertAdjacentHTML(position, html) {
        this.innerHTML = position === 'afterbegin' ? html + this.innerHTML : this.innerHTML + html;
    }
}

function runtime(selectors = {}) {
    const head = new ElementDouble('HEAD');
    const document = {
        baseURI: 'https://example.com/services',
        currentScript: null,
        documentElement: new ElementDouble('HTML'),
        head,
        readyState: 'complete',
        title: 'Original title',
        createElement: (tagName) => new ElementDouble(tagName),
        querySelector: (selector) => selectors[selector] ?? null,
    };
    const storage = new Map();
    const window = {
        __SITEWELL_PIXEL_TEST_MODE__: true,
        clearTimeout,
        localStorage: {
            getItem: (key) => storage.get(key) ?? null,
            setItem: (key, value) => storage.set(key, value),
        },
        location: { href: document.baseURI, hostname: 'example.com', origin: 'https://example.com', pathname: '/services' },
        setTimeout,
    };
    const context = { AbortController, Array, Date, JSON, Map, Promise, Set, URL, document, fetch, window };

    vm.runInNewContext(source, context);

    return { api: window.__SITEWELL_PIXEL_TEST_API__, document, head };
}

test('applies title, meta description, text, and H1 changes', () => {
    const paragraph = new ElementDouble('P');
    const heading = new ElementDouble('H1');
    const { api, document, head } = runtime({ '#intro': paragraph, h1: heading });

    api.applyPayload({ changes: [
        { id: 'opt_title', type: 'title', value: 'New title' },
        { id: 'opt_meta', type: 'meta_description', value: 'A better description' },
        { id: 'opt_h1', type: 'h1', value: 'Roof repairs' },
        { id: 'opt_text', type: 'text', selector: '#intro', value: 'Updated introduction' },
    ] });

    assert.equal(document.title, 'New title');
    assert.equal(heading.textContent, 'Roof repairs');
    assert.equal(paragraph.textContent, 'Updated introduction');
    assert.equal(head.childNodes[0].tagName, 'META');
    assert.equal(head.childNodes[0].getAttribute('content'), 'A better description');
});

test('allows only safe supported attribute changes', () => {
    const link = new ElementDouble('A');
    const image = new ElementDouble('IMG');
    const { api } = runtime({ '#link': link, '#image': image });

    api.applyChange({ id: 'opt_link', type: 'internal_link', selector: '#link', value: '/safe-page' });
    api.applyChange({ id: 'opt_external', type: 'internal_link', selector: '#link', value: 'https://attacker.example/page' });
    api.applyChange({ id: 'opt_bad_link', type: 'internal_link', selector: '#link', value: 'javascript:alert(1)' });
    api.applyChange({ id: 'opt_alt', type: 'image_alt', selector: '#image', value: 'Roof repair team' });
    api.applyChange({ id: 'opt_event', type: 'attribute', selector: '#link', attribute: 'onclick', value: 'alert(1)' });

    assert.equal(link.getAttribute('href'), '/safe-page');
    assert.equal(link.getAttribute('onclick'), null);
    assert.equal(image.getAttribute('alt'), 'Roof repair team');
    assert.equal(api.safeHref('data:text/html,test'), null);
});

test('inserts parsed JSON-LD as inert structured data', () => {
    const { api, head } = runtime();

    api.applyChange({
        id: 'opt_schema',
        type: 'json_ld',
        value: '{"@context":"https://schema.org","name":"</script><script>alert(1)</script>"}',
    });

    const script = head.childNodes[0];
    assert.equal(script.type, 'application/ld+json');
    assert.equal(script.getAttribute('data-sitewell-optimisation'), 'opt_schema');
    assert.equal(script.textContent.includes('<'), false);
    assert.deepEqual(JSON.parse(script.textContent), {
        '@context': 'https://schema.org',
        name: '</script><script>alert(1)</script>',
    });
});

test('isolates malformed optimisations and continues applying later changes', () => {
    const { api, document } = runtime();

    assert.doesNotThrow(() => api.applyPayload({ changes: [
        { id: 'opt_bad_json', type: 'json_ld', value: '{bad json' },
        { id: 'opt_title', type: 'title', value: 'Still applied' },
    ] }));
    assert.equal(document.title, 'Still applied');
    assert.doesNotThrow(() => api.applyPayload(null));
    assert.doesNotThrow(() => api.applyPayload({ changes: 'invalid' }));
});

test('throttles heartbeats to one per browser page each day', () => {
    const { api } = runtime();

    assert.equal(api.shouldReportHeartbeat('sw_abcdefghijklmnopqrstuvwxyz'), true);
    assert.equal(api.shouldReportHeartbeat('sw_abcdefghijklmnopqrstuvwxyz'), false);
});
