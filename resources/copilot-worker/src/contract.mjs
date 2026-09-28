export const protocolVersion = 1;
export const fixtureName = 'website-title';
export const modes = ['dry-run', 'probe', 'live'];

export class WorkerError extends Error {
    constructor(code) {
        super(code);
        this.code = code;
    }
}

export function validateRequest(request) {
    if (!request || request.protocolVersion !== protocolVersion
        || !/^[a-f0-9-]{36}$/i.test(request.runId ?? '')
        || ![fixtureName, 'repository-title'].includes(request.fixture) || !modes.includes(request.mode)
        || Object.keys(request).some(key => !['protocolVersion', 'runId', 'fixture', 'mode', 'limits', 'document'].includes(key))) {
        throw new WorkerError('invalid_request');
    }

    if (request.fixture === 'repository-title') {
        const document = request.document;
        if (request.mode !== 'live' || !document
            || Object.keys(document).some(key => !['path', 'original', 'title'].includes(key))
            || !/^(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9_-]+\.html$/.test(document.path ?? '')
            || typeof document.original !== 'string' || Buffer.byteLength(document.original) > 8192
            || (document.original.match(/<title>[^<]*<\/title>/g) ?? []).length !== 1
            || typeof document.title !== 'string' || !document.title.trim() || document.title.length > 200
            || /[\x00-\x1f\x7f]/.test(document.title)) {
            throw new WorkerError('invalid_document');
        }
    } else if (request.document !== undefined) {
        throw new WorkerError('invalid_request');
    }

    const limits = request.limits;
    if (!limits || !Number.isInteger(limits.timeoutSeconds) || limits.timeoutSeconds < 1 || limits.timeoutSeconds > 120
        || !Number.isInteger(limits.maxToolCalls) || limits.maxToolCalls < 1 || limits.maxToolCalls > 20
        || !Number.isInteger(limits.maxTokens) || limits.maxTokens < 1 || limits.maxTokens > 50000
        || Object.keys(limits).some(key => !['timeoutSeconds', 'maxToolCalls', 'maxTokens'].includes(key))) {
        throw new WorkerError('invalid_limits');
    }

    return request;
}

export function validateProvider(env) {
    const type = env.SITEWELL_MODEL_PROVIDER;
    const endpoints = { anthropic: 'https://api.anthropic.com', openai: 'https://api.openai.com/v1' };
    if (!endpoints[type] || !env.SITEWELL_MODEL_API_KEY || !/^[a-zA-Z0-9._:-]{1,150}$/.test(env.SITEWELL_MODEL_NAME ?? '')) {
        throw new WorkerError('provider_not_configured');
    }
    return { type, baseUrl: endpoints[type], apiKey: env.SITEWELL_MODEL_API_KEY, model: env.SITEWELL_MODEL_NAME };
}
