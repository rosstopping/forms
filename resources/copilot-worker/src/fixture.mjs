import { WorkerError } from './contract.mjs';

export const prompt = 'Use sitewell_fixture_read to inspect index.html. Replace only its title with Acme Plumbing | Doncaster using sitewell_fixture_write. Pass only the plain-text title, never the HTML document. Read once, write once, check once, then finish with a short confirmation.';

export function createFixture(maxToolCalls, signal, document) {
    const original = document?.original ?? '<!doctype html>\n<html><head><title>Home</title></head><body><h1>Acme Plumbing</h1></body></html>\n';
    const path = document?.path ?? 'index.html';
    const approvedTitle = document?.title ?? 'Acme Plumbing | Doncaster';
    const title = approvedTitle.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
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
                name: 'sitewell_fixture_write', skipPermission: true, defer: 'never', description: 'Set only the approved title. The tool escapes it and preserves all other HTML bytes automatically.',
                parameters: { type: 'object', properties: { path: { type: 'string', enum: [path] }, title: { type: 'string', enum: [approvedTitle] } }, required: ['path', 'title'], additionalProperties: false },
                handler: args => {
                    guard(args, ['path', 'title']); checkPath(args.path);
                    if (args.title !== approvedTitle) throw new WorkerError('title_not_allowed');
                    if (Buffer.byteLength(expected) > 8192) throw new WorkerError('file_limit');
                    content = expected;
                    return verification();
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
    await tools[0].handler({ path: 'index.html' });
    await tools[1].handler({ path: 'index.html', title: 'Acme Plumbing | Doncaster' });
    await tools[2].handler({});
}
