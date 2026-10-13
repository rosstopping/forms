import assert from 'node:assert/strict';
import test from 'node:test';
import { runTask } from '../src/run.mjs';

const request = {
    protocolVersion: 1,
    runId: 'content-generation-7',
    project: 'rosstopping/digizu',
    prompt: 'Improve the guide.',
    mode: 'medium',
    timeoutSeconds: 60,
    title: 'Sitewell content #7',
    labels: ['sitewell-content'],
};

test('runs the task as a private orb thread on the site repository and reports the thread early', async () => {
    let options;
    const sessions = [];
    const result = await runTask(request, {
        execute: async function* (call) {
            options = call.options;
            yield { type: 'system', subtype: 'init', session_id: 'T-abc' };
            yield { type: 'result', is_error: false, session_id: 'T-abc', result: 'https://github.com/rosstopping/digizu/pull/4', usage: { input_tokens: 10, output_tokens: 4, cache_read_input_tokens: 2 } };
        },
        onSession: (id) => sessions.push(id),
    });

    assert.deepEqual(sessions, ['T-abc']);
    assert.equal(options.executor, 'orb');
    assert.equal(options.project, 'rosstopping/digizu');
    assert.equal(options.visibility, 'private');
    assert.equal(result.status, 'completed');
    assert.equal(result.sessionId, 'T-abc');
    assert.deepEqual(result.usage, { inputTokens: 10, outputTokens: 4, cacheReadTokens: 2, cacheWriteTokens: 0 });
});

test('reports agent errors and missing results as failures', async () => {
    const failed = await runTask(request, {
        execute: async function* () {
            yield { type: 'result', is_error: true, session_id: 'T-x', error: 'No accessible Amp project matches "rosstopping/digizu"' };
        },
    });
    assert.equal(failed.status, 'failed');
    assert.equal(failed.error, 'agent_error');
    assert.match(failed.message, /No accessible Amp project/);

    const empty = await runTask(request, { execute: async function* () {} });
    assert.equal(empty.error, 'no_result');
});

test('rejects requests that do not name exactly one repository', async () => {
    await assert.rejects(runTask({ ...request, project: 'https://github.com/a/b --project other/repo' }, { execute: async function* () {} }), { code: 'invalid_request' });
});
