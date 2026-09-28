import { runFixture } from './run.mjs';
import { WorkerError } from './contract.mjs';

const controller = new AbortController();
process.once('SIGTERM', () => controller.abort());
process.once('SIGINT', () => controller.abort());

try {
    let input = '';
    for await (const chunk of process.stdin) {
        input += chunk;
        if (Buffer.byteLength(input) > 65536) throw new WorkerError('input_limit');
    }
    const result = await runFixture(JSON.parse(input), { signal: controller.signal });
    process.stdout.write(`${JSON.stringify(result)}\n`);
    process.exitCode = ['validated', 'runtime_ready'].includes(result.status) ? 0 : 1;
} catch (error) {
    process.stdout.write(`${JSON.stringify({ protocolVersion: 1, status: 'failed', error: error instanceof WorkerError ? error.code : 'invalid_request' })}\n`);
    process.exitCode = 1;
}
