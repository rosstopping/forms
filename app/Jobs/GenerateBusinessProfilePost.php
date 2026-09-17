<?php

namespace App\Jobs;

use App\Ai\Agents\BusinessProfilePostWriter;
use App\Models\BusinessProfilePost;
use App\Services\BusinessProfilePostSuggestions;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateBusinessProfilePost implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 3;

    public int $uniqueFor = 600;

    public bool $deleteWhenMissingModels = true;

    public function uniqueId(): string
    {
        return (string) $this->post->id;
    }

    public function __construct(public BusinessProfilePost $post) {}

    /**
     * Execute the job.
     */
    public function handle(BusinessProfilePostWriter $writer): void
    {
        $this->post->refresh();
        if (! in_array($this->post->status, [BusinessProfilePost::STATUS_GENERATING, BusinessProfilePost::STATUS_FAILED], true)) {
            return;
        }
        try {
            $connection = $this->post->connection()->with('website.domains')->firstOrFail();
            $response = $writer->prompt(json_encode(['business' => $connection->location_title ?: $connection->website->name, 'website' => $connection->website->primaryDomain()?->domain, 'topic' => $this->post->topic, 'brand_guidance' => $connection->brand_guidance, 'business_facts' => app(BusinessProfilePostSuggestions::class)->context($connection)], JSON_THROW_ON_ERROR));
            $this->post->update(['status' => BusinessProfilePost::STATUS_PENDING_APPROVAL, 'summary' => $response['summary'], 'call_to_action_type' => $response['call_to_action_type'] === 'NONE' ? null : $response['call_to_action_type'], 'call_to_action_url' => $response['call_to_action_url'] ?: null, 'error' => null]);
        } catch (Throwable $exception) {
            $this->post->update(['status' => BusinessProfilePost::STATUS_FAILED, 'error' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
