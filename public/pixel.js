(function () {
    'use strict';

    var ALLOWED_ELEMENTS = new Set([
        'A', 'ABBR', 'B', 'BLOCKQUOTE', 'BR', 'CODE', 'DIV', 'EM', 'FIGCAPTION', 'FIGURE',
        'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'HR', 'I', 'LI', 'OL', 'P', 'PRE', 'SMALL',
        'SPAN', 'STRONG', 'SUB', 'SUP', 'TABLE', 'TBODY', 'TD', 'TFOOT', 'TH', 'THEAD', 'TR', 'UL',
    ]);
    var REMOVED_ELEMENTS = new Set([
        'BASE', 'BUTTON', 'EMBED', 'FORM', 'IFRAME', 'INPUT', 'LINK', 'MATH', 'META', 'OBJECT',
        'OPTION', 'SCRIPT', 'SELECT', 'STYLE', 'SVG', 'TEXTAREA',
    ]);
    var ALLOWED_ATTRIBUTES = new Set(['aria-label', 'href', 'title']);
    var ALLOWED_CHANGE_ATTRIBUTES = new Set(['alt', 'aria-label', 'href', 'title']);
    var PIXEL_VERSION = '1.0.0';
    var script = document.currentScript;

    function safeHref(value, internalOnly) {
        if (typeof value !== 'string' || value.trim() === '') {
            return null;
        }

        try {
            var url = new URL(value, document.baseURI);

            if (url.protocol !== 'http:' && url.protocol !== 'https:') {
                return null;
            }

            if (internalOnly) {
                var targetHost = url.hostname.toLowerCase().replace(/^www\./, '');
                var currentHost = window.location.hostname.toLowerCase().replace(/^www\./, '');

                if (targetHost !== currentHost) {
                    return null;
                }
            }

            return value;
        } catch (_) {
            return null;
        }
    }

    function sanitizeNode(node) {
        Array.from(node.childNodes || []).forEach(function (child) {
            if (child.nodeType !== 1) {
                return;
            }

            if (REMOVED_ELEMENTS.has(child.tagName)) {
                child.remove();

                return;
            }

            if (!ALLOWED_ELEMENTS.has(child.tagName)) {
                sanitizeNode(child);
                child.replaceWith.apply(child, Array.from(child.childNodes));

                return;
            }

            Array.from(child.attributes).forEach(function (attribute) {
                var name = attribute.name.toLowerCase();
                var allowed = ALLOWED_ATTRIBUTES.has(name);

                if (!allowed || name === 'href' && safeHref(attribute.value) === null) {
                    child.removeAttribute(attribute.name);
                }
            });

            sanitizeNode(child);
        });
    }

    function sanitizeHtml(value) {
        var template = document.createElement('template');
        template.innerHTML = typeof value === 'string' ? value : '';
        sanitizeNode(template.content);

        return template.innerHTML;
    }

    function target(selector, fallback) {
        var selected = typeof selector === 'string' && selector.trim() !== '' ? selector : fallback;

        return selected ? document.querySelector(selected) : null;
    }

    function setMetaDescription(value) {
        var meta = document.querySelector('meta[name="description"]');

        if (!meta) {
            meta = document.createElement('meta');
            meta.setAttribute('name', 'description');
            (document.head || document.documentElement).appendChild(meta);
        }

        meta.setAttribute('content', value);
    }

    function setAttribute(change, name, internalOnly) {
        if (!ALLOWED_CHANGE_ATTRIBUTES.has(name)) {
            return;
        }

        var element = target(change.selector);

        if (!element) {
            return;
        }

        if (name === 'href' && safeHref(change.value, internalOnly) === null) {
            return;
        }

        element.setAttribute(name, change.value);
    }

    function setJsonLd(change) {
        var value = JSON.parse(change.value);

        if (value === null || typeof value !== 'object') {
            return;
        }

        var selector = 'script[type="application/ld+json"][data-sitewell-optimisation="' + change.id + '"]';
        var element = document.querySelector(selector) || document.createElement('script');
        element.type = 'application/ld+json';
        element.setAttribute('data-sitewell-optimisation', change.id);
        element.textContent = JSON.stringify(value).replace(/</g, '\\u003c');

        if (!element.parentNode) {
            (document.head || document.documentElement).appendChild(element);
        }
    }

    function applyChange(change) {
        if (!change || typeof change.id !== 'string' || typeof change.type !== 'string' || typeof change.value !== 'string') {
            return;
        }

        var element;
        var html;

        switch (change.type) {
            case 'title':
                document.title = change.value;
                break;
            case 'meta_description':
                setMetaDescription(change.value);
                break;
            case 'h1':
                element = target(change.selector, 'h1');
                if (element) element.textContent = change.value;
                break;
            case 'text':
                element = target(change.selector);
                if (element) element.textContent = change.value;
                break;
            case 'html':
                element = target(change.selector);
                if (element) element.innerHTML = sanitizeHtml(change.value);
                break;
            case 'append_html':
            case 'prepend_html':
                element = target(change.selector);
                html = sanitizeHtml(change.value);
                if (element && html !== '') {
                    element.insertAdjacentHTML(change.type === 'append_html' ? 'beforeend' : 'afterbegin', html);
                }
                break;
            case 'attribute':
                setAttribute(change, change.attribute);
                break;
            case 'image_alt':
                setAttribute(change, 'alt');
                break;
            case 'internal_link':
                setAttribute(change, 'href', true);
                break;
            case 'json_ld':
                setJsonLd(change);
                break;
        }
    }

    function applyPayload(payload) {
        if (!payload || !Array.isArray(payload.changes)) {
            return;
        }

        payload.changes.slice(0, 1000).forEach(function (change) {
            try {
                applyChange(change);
            } catch (_) {
                // Every optimisation is isolated so stale selectors or malformed values cannot stop later changes.
            }
        });
    }

    function domReady() {
        if (document.readyState !== 'loading') {
            return Promise.resolve();
        }

        return new Promise(function (resolve) {
            document.addEventListener('DOMContentLoaded', resolve, { once: true });
        });
    }

    function shouldReportHeartbeat(siteKey) {
        try {
            var date = new Date().toISOString().slice(0, 10);
            var page = window.location.origin + window.location.pathname;
            var storageKey = 'sitewell-pixel-heartbeat:' + siteKey + ':' + date + ':' + page;

            if (window.localStorage.getItem(storageKey)) {
                return false;
            }

            window.localStorage.setItem(storageKey, '1');

            return true;
        } catch (_) {
            return false;
        }
    }

    function reportHeartbeat(apiBase, siteKey) {
        if (!shouldReportHeartbeat(siteKey)) {
            return;
        }

        var endpoint = apiBase.replace(/\/$/, '') + '/' + encodeURIComponent(siteKey) + '/heartbeat';

        fetch(endpoint, {
            body: JSON.stringify({ url: window.location.href, version: PIXEL_VERSION }),
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            keepalive: true,
            method: 'POST',
        }).catch(function () {});
    }

    async function load() {
        var siteKey = script && script.dataset ? script.dataset.site : '';

        if (!/^sw_[a-z0-9]{20,}$/i.test(siteKey || '')) {
            return;
        }

        var apiBase = script.dataset.api || new URL('/api/pixel/', script.src).toString();
        var endpoint = apiBase.replace(/\/$/, '') + '/' + encodeURIComponent(siteKey) + '?url=' + encodeURIComponent(window.location.href);
        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        var timeout = window.setTimeout(function () {
            if (controller) controller.abort();
        }, 4000);

        try {
            var response = await fetch(endpoint, {
                headers: { Accept: 'application/json' },
                signal: controller ? controller.signal : undefined,
            });

            if (!response.ok) {
                return;
            }

            var payload = await response.json();
            await domReady();
            applyPayload(payload);
            reportHeartbeat(apiBase, siteKey);
        } catch (_) {
            // The customer website must remain unaffected when Sitewell is unavailable.
        } finally {
            window.clearTimeout(timeout);
        }
    }

    if (window.__SITEWELL_PIXEL_TEST_MODE__) {
        window.__SITEWELL_PIXEL_TEST_API__ = {
            applyChange: applyChange,
            applyPayload: applyPayload,
            safeHref: safeHref,
            shouldReportHeartbeat: shouldReportHeartbeat,
        };

        return;
    }

    if (window.__SITEWELL_PIXEL_LOADED__) {
        return;
    }

    window.__SITEWELL_PIXEL_LOADED__ = true;
    load();
}());
