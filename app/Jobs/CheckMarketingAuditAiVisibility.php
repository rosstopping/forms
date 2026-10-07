<?php

namespace App\Jobs;

use App\Models\WebsiteAudit;
use App\Services\MarketingAuditAiVisibility;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CheckMarketingAuditAiVisibility implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public int $uniqueFor = 300;

    public function __construct(public WebsiteAudit $audit) {}

    public function uniqueId(): string
    {
        return (string) $this->audit->id;
    }

    public function handle(MarketingAuditAiVisibility $visibility): void
    {
        $audit = $this->audit->fresh();
        $questions = data_get($audit?->insights, 'ai_visibility.questions', []);
        if ($audit === null || $audit->status !== WebsiteAudit::STATUS_COMPLETED
            || data_get($audit->insights, 'full_report.status') !== 'completed'
            || data_get($audit->insights, 'ai_visibility.status') !== 'pending'
            || ! is_array($questions) || $questions === []) {
            return;
        }
        if (! $visibility->available()) {
            $this->failed(null);

            return;
        }

        $results = [];
        foreach (array_slice($questions, 0, 2) as $question) {
            if (! is_string($question)) {
                continue;
            }
            try {
                $results[] = $visibility->check($audit->domain, $question);
            } catch (Throwable $exception) {
                report($exception);
                $results[] = ['status' => 'unavailable', 'question' => $question];
            }
        }

        $audit->update(['insights' => [...$audit->insights, 'ai_visibility' => [
            'status' => 'completed',
            'questions' => $questions,
            'results' => $results,
        ]]]);
    }

    public function failed(?Throwable $exception): void
    {
        $audit = $this->audit->fresh();
        if ($audit !== null && data_get($audit->insights, 'ai_visibility.status') === 'pending') {
            $audit->update(['insights' => [...$audit->insights, 'ai_visibility' => [
                'status' => 'unavailable',
                'questions' => data_get($audit->insights, 'ai_visibility.questions', []),
            ]]]);
        }
    }
}
