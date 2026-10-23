<?php

namespace App\Console\Commands;

use App\Services\SitewellAgentClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;

#[Signature('agent:task {action : create, show, report or blocked} {website : Sitewell website ID} {task? : Agent task ID for show/report/blocked} {--proposal= : JSON proposal file for create} {--pr= : Draft GitHub PR URL for report} {--summary= : Review summary for report} {--checks= : JSON file containing a list of checks performed} {--reason= : Reason for blocked work} {--checkout= : Actual local checkout/worktree used for the draft PR}')]
#[Description('Reserve due empty-queue work or report a checked draft PR; never merges or publishes')]
class ManageAgentTask extends Command
{
    public function handle(SitewellAgentClient $client): int
    {
        $action = $this->argument('action');
        $website = $this->argument('website');
        $taskId = $this->argument('task');
        if (! in_array($action, ['create', 'show', 'report', 'blocked'], true) || ! preg_match('/^[1-9][0-9]*$/D', $website)
            || ($action !== 'create' && (! is_string($taskId) || ! preg_match('/^[1-9][0-9]*$/D', $taskId)))) {
            $this->error('Choose create/show/report/blocked with numeric website and task IDs.');

            return self::FAILURE;
        }
        try {
            $path = 'websites/'.$website.'/tasks';
            $data = match ($action) {
                'create' => $this->create($client, $path),
                'show' => $client->get($path.'/'.$taskId),
                'report' => $this->report($client, $path.'/'.$taskId),
                'blocked' => $client->post($path.'/'.$taskId.'/blocked', ['reason' => $this->option('reason')]),
            };
        } catch (RuntimeException|JsonException $exception) {
            $this->error($exception instanceof JsonException ? 'Use a valid JSON proposal/checks file.' : $exception->getMessage());

            return self::FAILURE;
        }
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function create(SitewellAgentClient $client, string $path): array
    {
        $proposal = $this->jsonFile($this->option('proposal'));
        if (($proposal['host'] ?? null) !== 'local' || ! is_string($proposal['path'] ?? null) || ! is_string($proposal['repository'] ?? null)) {
            throw new RuntimeException('Choose a recorded project on this local Codex host.');
        }
        $this->verifyCheckout($proposal['path'], $proposal['repository']);

        return $client->post($path, $proposal);
    }

    /** @return array<string, mixed> */
    private function report(SitewellAgentClient $client, string $path): array
    {
        $task = $client->get($path)['task'] ?? null;
        if (! is_array($task) || ! ($task['reservation_active'] ?? false) || ! in_array($task['status'] ?? null, ['in_progress', 'draft_pr_reported'], true)) {
            throw new RuntimeException('This task has no active reservation or is blocked.');
        }
        $checkout = $this->option('checkout') ?: ($task['project']['path'] ?? null);
        if (($task['project']['host'] ?? null) !== 'local' || ! is_string($checkout)) {
            throw new RuntimeException('Report the work from its verified local Codex checkout.');
        }
        $this->verifyCheckout($checkout, $task['repository']);
        $url = $this->option('pr');
        $prefix = 'https://github.com/'.$task['repository'].'/pull/';
        if (! is_string($url) || ! str_starts_with($url, $prefix) || ! preg_match('/^[1-9][0-9]*$/D', substr($url, strlen($prefix)))) {
            throw new RuntimeException('Use a canonical PR URL for this task’s repository.');
        }
        $branch = trim($this->verifiedProcess($checkout, ['git', 'branch', '--show-current']));
        $sha = trim($this->verifiedProcess($checkout, ['git', 'rev-parse', 'HEAD']));
        if ($branch !== $task['branch'] || trim($this->verifiedProcess($checkout, ['git', 'status', '--porcelain'])) !== '') {
            throw new RuntimeException('Use the reserved branch with all intended changes committed before reporting the draft.');
        }
        $pr = json_decode($this->verifiedProcess($checkout, ['gh', 'api', 'repos/'.$task['repository'].'/pulls/'.substr($url, strlen($prefix))]), true, flags: JSON_THROW_ON_ERROR);
        if (($pr['draft'] ?? false) !== true || ($pr['state'] ?? null) !== 'open' || ! empty($pr['merged_at'])
            || ($pr['head']['ref'] ?? null) !== $task['branch'] || ($pr['head']['sha'] ?? null) !== $sha
            || strcasecmp($pr['base']['repo']['full_name'] ?? '', $task['repository']) !== 0
            || strcasecmp($pr['head']['repo']['full_name'] ?? '', $task['repository']) !== 0 || ($pr['changed_files'] ?? 0) < 1) {
            throw new RuntimeException('GitHub must confirm an open, unmerged draft with actual changes on the reserved repository, branch and commit.');
        }

        return $client->post($path.'/draft-pr', ['pull_request_url' => $url, 'is_draft' => true, 'state' => 'open', 'branch' => $branch,
            'head_sha' => $sha, 'changed_files' => $pr['changed_files'], 'summary' => $this->option('summary'), 'checks' => $this->jsonFile($this->option('checks'))]);
    }

    private function verifyCheckout(string $path, string $repository): void
    {
        if (! is_dir($path) || ! preg_match('~^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$~D', $repository)) {
            throw new RuntimeException('The local checkout or GitHub repository is unavailable.');
        }
        $root = trim($this->verifiedProcess($path, ['git', 'rev-parse', '--show-toplevel']));
        $remote = trim($this->verifiedProcess($path, ['git', 'remote', 'get-url', 'origin']));
        if (realpath($root) !== realpath($path) || ! preg_match('~^(?:git@github\.com:|https://github\.com/)([a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+?)(?:\.git)?$~D', $remote, $matches)
            || strcasecmp($matches[1], $repository) !== 0) {
            throw new RuntimeException('The checkout root and GitHub origin must match the recorded website project.');
        }
    }

    /** @param list<string> $command */
    private function verifiedProcess(string $path, array $command): string
    {
        $result = Process::path($path)->timeout(30)->run($command);
        if (! $result->successful()) {
            throw new RuntimeException('Could not verify the local checkout or GitHub draft. No result was recorded.');
        }

        return $result->output();
    }

    /** @return array<string|int, mixed> */
    private function jsonFile(?string $path): array
    {
        if (! $path || ! is_file($path) || ! is_readable($path) || filesize($path) > 64000) {
            throw new RuntimeException('Provide a readable JSON file no larger than 64 KB.');
        }
        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('The JSON file must contain an object or list.');
        }

        return $data;
    }
}
