<?php

namespace App\Jobs;

use App\Ai\Agents\BusinessProfilePostWriter;
use App\Models\BusinessProfilePost;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateBusinessProfilePost implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 3;

    public function __construct(public BusinessProfilePost $post) {}

    /**
     * Execute the job.
     */
    public function handle(BusinessProfilePostWriter $writer): void
    {
        try {
            $connection = $this->post->connection()->with('website.domains')->firstOrFail();
            $response = $writer->prompt(json_encode(['business' => $connection->location_title ?: $connection->website->name, 'website' => $connection->website->primaryDomain()?->domain, 'topic' => $this->post->topic, 'brand_guidance' => $connection->brand_guidance], JSON_THROW_ON_ERROR));
            $this->post->update(['status' => BusinessProfilePost::STATUS_PENDING_APPROVAL, 'summary' => $response['summary'], 'call_to_action_type' => $response['call_to_action_type'] === 'NONE' ? null : $response['call_to_action_type'], 'call_to_action_url' => $response['call_to_action_url'] ?: null, 'error' => null]);
        } catch (Throwable $exception) {
            $this->post->update(['status' => BusinessProfilePost::STATUS_FAILED, 'error' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
