<?php

namespace App\Services;

use App\Models\ContentRequest;
use App\Models\WebsiteRepository;
use Illuminate\Support\Carbon;
use Throwable;

class ContentRepositoryPreflight
{
    public function __construct(private GithubAppClient $github) {}

    /** @return array{state: string, reason: string, eligible_at: ?string} */
    public function check(ContentRequest $request, WebsiteRepository $repository, ?float $deadline = null): array
    {
        $files = $request->dependencies['files'] ?? [];
        $result = ['state' => 'ready', 'reason' => 'Declared repository dependencies are eligible.', 'eligible_at' => null];
        if ($files === []) {
            return $result;
        }

        try {
            if ($deadline && microtime(true) >= $deadline) {
                throw new \RuntimeException('Repository preflight reached its dispatch time budget.');
            }
            $evidence = $this->github->contentDependencyHistory($repository, $files, $deadline);
            $until = null;
            $blockedFiles = [];
            foreach ($evidence['changes'] as $change) {
                $date = Carbon::parse($change['changed_at'])->addDays(14);
                if ($date->isFuture()) {
                    $blockedFiles[] = $change['file'];
                    $until = ! $until || $date->greaterThan($until) ? $date : $until;
                }
            }
            if ($until) {
                $result = ['state' => 'cooldown', 'reason' => 'Required repository files changed recently: '.implode(', ', array_unique($blockedFiles)), 'eligible_at' => $until->toIso8601String()];
            }
            if ($evidence['reviews'] !== []) {
                $result = ['state' => 'review', 'reason' => 'Required repository files overlap open pull requests: '.implode(', ', $evidence['reviews']), 'eligible_at' => null];
            }
        } catch (Throwable $exception) {
            report($exception);
            $result = ['state' => 'preflight', 'reason' => 'Repository dependencies could not be verified. They will be checked again before the next preparation attempt.', 'eligible_at' => null];
        }

        $request->update(['preflight' => [...$result, 'checked_at' => now()->toIso8601String(), 'repository_id' => $repository->id]]);

        return $result;
    }
}
