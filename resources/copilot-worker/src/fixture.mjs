import { WorkerError } from './contract.mjs';

export const prompt = 'Use sitewell_fixture_read to inspect index.html. Replace only its title with Acme Plumbing | Doncaster using sitewell_fixture_write. Preserve every other byte. Use sitewell_fixture_check to verify the result. Do not use other tools.';

export function createFixture(maxToolCalls, signal, document) {
    const original = document?.original ?? '<!doctype html>\n<html><head><title>Home</title></head><body><h1>Acme Plumbing</h1></body></html>\n';
    const path = document?.path ?? 'index.html';
    const title = (document?.title ?? 'Acme Plumbing | Doncaster').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
    const expected = original.replace(/<title>[^<]*<\/title>/, () => `<title>${title}</title>`);
    let content = original;
    let calls = 0;
    const guard = (args, keys) => {
        if (signal.aborted) throw new WorkerError('cancelled');
        if (++calls > maxToolCalls) throw new WorkerError('tool_limit');
        if (!args || Object.keys(args).some(key => !keys.includes(key))) throw new WorkerError('invalid_tool_arguments');
    };
    const checkPath = requestedPath => {
        if (requestedPath !== path) throw new WorkerError('path_not_allowed');
    };
    const verification = () => ({ name: 'title-only-change', passed: content === expected });
    return {
        tools: [
            {
                name: 'sitewell_fixture_read', skipPermission: true, defer: 'never', description: 'Read the approved HTML document held in memory.',
                parameters: { type: 'object', properties: { path: { type: 'string', enum: [path] } }, required: ['path'], additionalProperties: false },
                handler: args => { guard(args, ['path']); checkPath(args.path); return content; },
            },
            {
                name: 'sitewell_fixture_write', skipPermission: true, defer: 'never', description: 'Replace the approved HTML document held in memory.',
                parameters: { type: 'object', properties: { path: { type: 'string', enum: [path] }, content: { type: 'string', maxLength: 8192 } }, required: ['path', 'content'], additionalProperties: false },
                handler: args => {
                    guard(args, ['path', 'content']); checkPath(args.path);
                    if (typeof args.content !== 'string' || Buffer.byteLength(args.content) > 8192) throw new WorkerError('file_limit');
                    content = args.content;
                    return 'Saved';
                },
            },
            {
                name: 'sitewell_fixture_check', skipPermission: true, defer: 'never', description: 'Run the trusted title-only fixture check. This does not execute repository code.',
                parameters: { type: 'object', properties: {}, additionalProperties: false },
                handler: args => { guard(args, []); return verification(); },
            },
        ],
        result: () => ({
            verification: [verification()],
            changes: content === original ? [] : [{ path, before: original, after: content }],
            toolCalls: calls,
        }),
    };
}

export async function simulateFixture(tools) {
    const originalContent = await tools[0].handler({ path: 'index.html' });
    await tools[1].handler({ path: 'index.html', content: originalContent.replace('<title>Home</title>', '<title>Acme Plumbing | Doncaster</title>') });
    await tools[2].handler({});
}
