import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { randomUUID } from 'node:crypto';
import { runFixture } from '../src/run.mjs';
import { createSdkAdapter } from '../src/sdk.mjs';

// Real SDK/runtime and tool dispatch; the model responses are scripted locally.
test('bundled SDK edits and validates the fixture through a local model stub', { timeout: 30000 }, async () => {
    let calls = 0;
    const server = createServer(async (req, res) => {
        let raw = '';
        for await (const chunk of req) raw += chunk;
        const body = JSON.parse(raw);
        assert.deepEqual(body.tools.map(tool => tool.function.name).sort(), ['sitewell_fixture_check', 'sitewell_fixture_read', 'sitewell_fixture_write']);
        const actions = [
            ['sitewell_fixture_read', { path: 'index.html' }],
            ['sitewell_fixture_write', { path: 'index.html', content: '<!doctype html>\n<html><head><title>Acme Plumbing | Doncaster</title></head><body><h1>Acme Plumbing</h1></body></html>\n' }],
            ['sitewell_fixture_check', {}],
        ];
        const action = actions[calls++];
        const toolName = action && body.tools?.find(tool => tool.function?.name.endsWith(action[0]))?.function?.name;
        if (action && !toolName) {
            res.writeHead(400, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: { message: 'Fixture tool missing', type: 'invalid_request_error' } }));
            return;
        }
        const message = action ? {
            role: 'assistant', content: null,
            tool_calls: [{ id: `call_${calls}`, type: 'function', function: { name: toolName, arguments: JSON.stringify(action[1]) } }],
        } : { role: 'assistant', content: 'Fixture updated and checked.' };
        const usage = { prompt_tokens: 100, completion_tokens: 20, total_tokens: 120 };
        const base = { id: `stub-${calls}`, created: Math.floor(Date.now() / 1000), model: 'fixture-model' };
        if (body.stream) {
            res.writeHead(200, { 'Content-Type': 'text/event-stream' });
            const delta = action ? { ...message, tool_calls: [{ index: 0, ...message.tool_calls[0] }] } : message;
            res.write(`data: ${JSON.stringify({ ...base, object: 'chat.completion.chunk', choices: [{ index: 0, delta, finish_reason: null }] })}\n\n`);
            res.write(`data: ${JSON.stringify({ ...base, object: 'chat.completion.chunk', choices: [{ index: 0, delta: {}, finish_reason: action ? 'tool_calls' : 'stop' }], usage })}\n\n`);
            res.end('data: [DONE]\n\n');
        } else {
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ ...base, object: 'chat.completion', choices: [{ index: 0, message, finish_reason: action ? 'tool_calls' : 'stop' }], usage }));
        }
    });
    await new Promise((resolve, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', resolve); });
    try {
        const result = await runFixture({ protocolVersion: 1, runId: randomUUID(), fixture: 'website-title', mode: 'live', limits: { timeoutSeconds: 20, maxToolCalls: 10, maxTokens: 10000 } }, {
            env: { SITEWELL_MODEL_PROVIDER: 'openai', SITEWELL_MODEL_NAME: 'fixture-model', SITEWELL_MODEL_API_KEY: 'local-test-only' },
            adapterFactory: async () => {
                const adapter = await createSdkAdapter();
                return { close: () => adapter.close(), run: args => adapter.run({ ...args, provider: { ...args.provider, baseUrl: `http://127.0.0.1:${server.address().port}/v1`, wireApi: 'completions' } }) };
            },
        });
        assert.equal(result.status, 'validated', JSON.stringify(result));
        assert.equal(result.toolCalls, 3);
        assert.equal(calls, 4);
        assert.ok(result.usage.events > 0);
    } finally {
        server.closeAllConnections();
        await new Promise(resolve => server.close(resolve));
    }
});
