import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { WorkerError } from './contract.mjs';

export async function createSdkAdapter({ CopilotClient } = {}) {
    CopilotClient ??= (await import('@github/copilot-sdk')).CopilotClient;
    const directory = await mkdtemp(join(tmpdir(), 'sitewell-sdk-'));
    let client;
    let session;
    return {
        async run({ request, provider, tools, prompt, onUsage, signal }) {
            client = new CopilotClient({
                mode: 'empty',
                workingDirectory: directory,
                baseDirectory: join(directory, 'state'),
                useLoggedInUser: false,
                logLevel: 'none',
                env: { PATH: process.env.PATH, HOME: directory, TMPDIR: directory, XDG_CONFIG_HOME: directory },
            });
            await client.start();
            if (signal.aborted) throw new WorkerError('cancelled');
            if (request.mode === 'probe') {
                await client.ping('sitewell-fixture-probe');
                return;
            }
            const { model, ...providerConfig } = provider;
            session = await client.createSession({
                sessionId: request.runId,
                model,
                provider: providerConfig,
                tools,
                availableTools: tools.map(tool => `custom:${tool.name}`),
                onPermissionRequest: () => ({ kind: 'denied-no-approval-rule-and-could-not-request-from-user' }),
                skipCustomInstructions: true,
                infiniteSessions: { enabled: false },
                systemMessage: { mode: 'replace', content: 'You edit a synthetic website fixture using only the explicitly supplied custom tools. Follow the task exactly.' },
            });
            if (signal.aborted) throw new WorkerError('cancelled');
            session.on('assistant.usage', event => onUsage(event.data));
            await session.sendAndWait({ prompt }, request.limits.timeoutSeconds * 1000);
        },
        async close() {
            if (client) {
                // Force-stop also covers a hung startup or an in-flight model request.
                await client.forceStop().catch(() => {});
            }
            await rm(directory, { recursive: true, force: true });
        },
    };
}
