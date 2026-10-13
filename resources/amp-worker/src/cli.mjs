import { execute } from '@ampcode/sdk';
import { runTask, WorkerError } from './run.mjs';

const controller = new AbortController();
process.once('SIGTERM', () => controller.abort());
process.once('SIGINT', () => controller.abort());
const emit = (event) => process.stdout.write(`${JSON.stringify(event)}\n`);

try {
    let input = '';
    for await (const chunk of process.stdin) {
        input += chunk;
        if (Buffer.byteLength(input) > 524288) {
            throw new WorkerError('input_limit');
        }
    }
    const result = await runTask(JSON.parse(input), {
        execute,
        signal: controller.signal,
        onSession: (sessionId) => emit({ event: 'session', sessionId }),
    });
    emit({ event: 'result', ...result });
    process.exitCode = result.status === 'completed' ? 0 : 1;
} catch (error) {
    emit({ event: 'result', protocolVersion: 1, status: 'failed', error: error instanceof WorkerError ? error.code : 'invalid_request' });
    process.exitCode = 1;
}
