<?php

namespace App\Jobs;

use App\Models\GoogleAdsCampaignDraft;
use App\Services\GoogleAdsCampaignCreator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Throwable;

class CreateGoogleAdsCampaign implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 300;

    public function __construct(public int $draftId) {}

    public function uniqueId(): string
    {
        return (string) $this->draftId;
    }

    public function handle(GoogleAdsCampaignCreator $campaigns): void
    {
        $draft = GoogleAdsCampaignDraft::query()->findOrFail($this->draftId);

        if ($draft->status !== GoogleAdsCampaignDraft::STATUS_PENDING) {
            return;
        }

        try {
            $campaigns->create($draft);
        } catch (Throwable $exception) {
            $draft->refresh();
            $draft->update([
                'status' => $draft->status === GoogleAdsCampaignDraft::STATUS_PENDING
                    ? GoogleAdsCampaignDraft::STATUS_FAILED
                    : $draft->status,
                'error' => $this->errorMessage($exception),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $draft = GoogleAdsCampaignDraft::query()->find($this->draftId);

        if (! $draft || $draft->status === GoogleAdsCampaignDraft::STATUS_CREATED) {
            return;
        }

        $draft->update([
            'status' => $draft->status === GoogleAdsCampaignDraft::STATUS_PENDING
                ? GoogleAdsCampaignDraft::STATUS_FAILED
                : $draft->status,
            'error' => $draft->error ?: 'Campaign creation stopped. Check its status in Google Ads before trying again.',
        ]);
    }

    protected function errorMessage(Throwable $exception): string
    {
        if ($exception instanceof RequestException) {
            $providerMessage = data_get($exception->response->json(), 'error.message');

            if (is_string($providerMessage) && $providerMessage !== '') {
                return Str::limit($providerMessage, 300);
            }
        }

        return $exception instanceof ConnectionException
            ? 'Google Ads did not respond in time.'
            : 'Google Ads could not create this campaign. Check the account and campaign settings.';
    }
}
