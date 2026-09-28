import test from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { existsSync } from 'node:fs';
import { runFixture } from '../src/run.mjs';
import { createFixture, simulateFixture } from '../src/fixture.mjs';
import { createSdkAdapter } from '../src/sdk.mjs';
import { validateRequest, validateProvider } from '../src/contract.mjs';

const request = (mode = 'dry-run') => ({ protocolVersion: 1, runId: randomUUID(), fixture: 'website-title', mode, limits: { timeoutSeconds: 1, maxToolCalls: 10, maxTokens: 1000 } });
const env = { SITEWELL_MODEL_PROVIDER: 'anthropic', SITEWELL_MODEL_NAME: 'test-model', SITEWELL_MODEL_API_KEY: 'never-print-me' };

test('simulation exercises real tools and verification without loading an SDK adapter', async () => {
    const result = await runFixture(request(), { adapterFactory: () => assert.fail('SDK must not start') });
    assert.equal(result.status, 'validated');
    assert.equal(result.changes.length, 1);
    assert.equal(result.toolCalls, 3);
    assert.equal(result.usage.events, 0);
});

test('contract rejects arbitrary files, commands, modes and excessive limits', () => {
    for (const change of [{ files: {} }, { command: 'sh' }, { mode: 'shell' }, { fixture: '../.env' }, { runId: 'invalid' }, { limits: { timeoutSeconds: 99999 } }]) {
        assert.throws(() => validateRequest({ ...request(), ...change }));
    }
    assert.throws(() => validateProvider({ ...env, SITEWELL_MODEL_PROVIDER: 'unknown' }));
    assert.throws(() => validateProvider({ ...env, SITEWELL_MODEL_API_KEY: '' }));
});

test('fixture tools reject host paths and oversized files', () => {
    const { tools } = createFixture(20, new AbortController().signal);
    for (const path of ['../.env', '/etc/passwd', 'index.html/../.env', 'index.html\0', 'https://example.com']) {
        assert.throws(() => tools[0].handler({ path }), /path_not_allowed/);
    }
    assert.throws(() => tools[1].handler({ path: 'index.html', content: 'a'.repeat(8193) }), /file_limit/);
    assert.throws(() => tools[2].handler({ command: 'node evil.js' }), /invalid_tool_arguments/);
});

test('tool limits and cancellation stop edits', () => {
    const controller = new AbortController();
    const { tools } = createFixture(1, controller.signal);
    tools[0].handler({ path: 'index.html' });
    assert.throws(() => tools[0].handler({ path: 'index.html' }), /tool_limit/);
    controller.abort();
    assert.throws(() => tools[1].handler({ path: 'index.html', content: '' }), /cancelled/);
});

test('live verification is independent of agent claims and always closes the adapter', async () => {
    let closed = false;
    const result = await runFixture(request('live'), { env, adapterFactory: async () => ({
        run: async () => 'Everything passed!', close: async () => { closed = true; },
    }) });
    assert.equal(result.status, 'validation_failed');
    assert.equal(closed, true);
});

test('usage accumulates once per API call and is not represented as a dollar estimate', async () => {
    const result = await runFixture(request('live'), { env, adapterFactory: async () => ({
        run: async ({ tools, onUsage }) => {
            onUsage({ apiCallId: 'one', inputTokens: 100, outputTokens: 20, cacheReadTokens: 10 });
            onUsage({ apiCallId: 'one', inputTokens: 100, outputTokens: 20 });
            await simulateFixture(tools);
        }, close: async () => {},
    }) });
    assert.equal(result.status, 'validated');
    assert.equal(result.usage.inputTokens, 100);
    assert.equal(result.usage.cacheReadTokens, 10);
    assert.equal(result.usage.events, 1);
});

test('token threshold cancels work and closes adapter', async () => {
    let closed = false;
    const result = await runFixture(request('live'), { env, adapterFactory: async () => ({
        run: async ({ onUsage }) => { onUsage({ inputTokens: 1001 }); await new Promise(() => {}); },
        close: async () => { closed = true; },
    }) });
    assert.equal(result.error, 'token_limit');
    assert.equal(closed, true);
});

test('timeout shuts down a stalled SDK', async () => {
    let closed = false;
    const result = await runFixture(request('live'), { env, adapterFactory: async () => ({
        run: async () => new Promise(() => {}), close: async () => { closed = true; },
    }) });
    assert.equal(result.error, 'time_limit');
    assert.equal(closed, true);
});

test('external cancellation shuts down a running SDK', async () => {
    const controller = new AbortController();
    let closed = false;
    const result = await runFixture(request('live'), { env, signal: controller.signal, adapterFactory: async () => ({
        run: async () => { controller.abort(); await new Promise(() => {}); }, close: async () => { closed = true; },
    }) });
    assert.equal(result.error, 'cancelled');
    assert.equal(closed, true);
});

test('SDK failures return a stable code instead of credentials or provider response bodies', async () => {
    const result = await runFixture(request('live'), { env, adapterFactory: async () => ({
        run: async () => { throw new Error(`Authorization: ${env.SITEWELL_MODEL_API_KEY}`); }, close: async () => {},
    }) });
    assert.equal(result.error, 'sdk_execution_failed');
    assert.doesNotMatch(JSON.stringify(result), /never-print-me/);
});

test('SDK adapter isolates runtime state, disables ambient auth and exposes only fixture tools', async () => {
    let options;
    let config;
    let stopped = false;
    class FakeClient {
        constructor(value) { options = value; }
        async start() {}
        async createSession(value) { config = value; return { on() {}, async sendAndWait() {} }; }
        async forceStop() { stopped = true; }
    }
    const adapter = await createSdkAdapter({ CopilotClient: FakeClient });
    const fixture = createFixture(10, new AbortController().signal);
    await adapter.run({ request: request('live'), provider: validateProvider(env), tools: fixture.tools, prompt: 'fixture', onUsage() {}, signal: new AbortController().signal });
    assert.equal(options.mode, 'empty');
    assert.equal(options.useLoggedInUser, false);
    assert.deepEqual(Object.keys(options.env).sort(), ['HOME', 'PATH', 'TMPDIR', 'XDG_CONFIG_HOME']);
    assert.equal(config.skipCustomInstructions, true);
    assert.deepEqual(config.availableTools, ['custom:sitewell_fixture_read', 'custom:sitewell_fixture_write', 'custom:sitewell_fixture_check']);
    assert.match(config.onPermissionRequest().kind, /^denied/);
    assert.equal(existsSync(options.workingDirectory), true);
    await adapter.close();
    assert.equal(stopped, true);
    assert.equal(existsSync(options.workingDirectory), false);
});
