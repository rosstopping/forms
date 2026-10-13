const MODES = new Set(['low', 'medium', 'high', 'ultra']);
const REPOSITORY = /^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/;

export class WorkerError extends Error {
    constructor(code) {
        super(code);
        this.code = code;
    }
}

/** Validate the versioned request Sitewell sends on stdin. */
export function validateRequest(request) {
    const valid = request !== null && typeof request === 'object'
        && request.protocolVersion === 1
        && typeof request.runId === 'string' && /^[a-z0-9-]{1,100}$/.test(request.runId)
        && typeof request.project === 'string' && REPOSITORY.test(request.project)
        && typeof request.prompt === 'string' && request.prompt.length > 0 && request.prompt.length <= 200000
        && MODES.has(request.mode)
        && Number.isInteger(request.timeoutSeconds) && request.timeoutSeconds >= 60 && request.timeoutSeconds <= 14400
        && typeof request.title === 'string' && request.title.length > 0 && request.title.length <= 256
        && Array.isArray(request.labels) && request.labels.every((label) => typeof label === 'string' && /^[a-z0-9][a-z0-9-]{0,49}$/.test(label));

    if (!valid) {
        throw new WorkerError('invalid_request');
    }

    return request;
}

function usageFrom(usage) {
    const count = (value) => (Number.isFinite(value) && value > 0 ? Math.floor(value) : 0);

    return {
        inputTokens: count(usage?.input_tokens),
        outputTokens: count(usage?.output_tokens),
        cacheReadTokens: count(usage?.cache_read_input_tokens),
        cacheWriteTokens: count(usage?.cache_creation_input_tokens),
    };
}

/**
 * Run one Sitewell content task as an Amp orb thread on the site's Amp project.
 * The orb, not this process, holds the repository checkout and GitHub credentials.
 */
export async function runTask(input, { execute, signal, onSession = () => {} }) {
    const request = validateRequest(input);
    const started = Date.now();
    const controller = new AbortController();
    let timedOut = false;
    const timer = setTimeout(() => {
        timedOut = true;
        controller.abort();
    }, request.timeoutSeconds * 1000);
    const cancel = () => controller.abort();
    signal?.addEventListener('abort', cancel, { once: true });
    let sessionId = null;
    const finish = (fields) => ({
        protocolVersion: 1,
        runId: request.runId,
        sessionId,
        elapsedMs: Date.now() - started,
        usage: usageFrom(null),
        result: null,
        error: null,
        message: null,
        ...fields,
    });

    try {
        const messages = execute({
            prompt: request.prompt,
            signal: controller.signal,
            options: {
                executor: 'orb',
                project: request.project,
                visibility: 'private',
                mode: request.mode,
                title: request.title,
                labels: request.labels,
                noArchiveAfterExecute: true,
            },
        });
        for await (const message of messages) {
            if (sessionId === null && typeof message?.session_id === 'string' && message.session_id !== '') {
                sessionId = message.session_id;
                onSession(sessionId);
            }
            if (message?.type === 'result') {
                return finish(message.is_error
                    ? { status: 'failed', error: 'agent_error', message: String(message.error ?? '').slice(0, 500), usage: usageFrom(message.usage) }
                    : { status: 'completed', result: String(message.result ?? '').slice(0, 20000), usage: usageFrom(message.usage) });
            }
        }

        return finish({ status: 'failed', error: 'no_result' });
    } catch (error) {
        const code = timedOut ? 'time_limit' : (signal?.aborted ? 'cancelled' : 'execution_failed');

        return finish({ status: 'failed', error: code, message: String(error?.message ?? '').slice(0, 500) });
    } finally {
        clearTimeout(timer);
        signal?.removeEventListener('abort', cancel);
    }
}
