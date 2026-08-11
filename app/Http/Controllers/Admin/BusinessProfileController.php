<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AuditBusinessProfile;
use App\Jobs\GenerateBusinessProfileReviewReply;
use App\Models\BusinessProfileAudit;
use App\Models\BusinessProfileReview;
use App\Models\Website;
use App\Services\BusinessProfileClient;
use App\Services\BusinessProfileOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Throwable;

class BusinessProfileController extends Controller
{
    public function __construct(protected BusinessProfileOAuthClient $oauth, protected BusinessProfileClient $client) {}

    public function connect(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $state = Crypt::encryptString(json_encode(['website_id' => $website->id, 'user_id' => $request->user()->id], JSON_THROW_ON_ERROR));

        return Redirect::away($this->oauth->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string'], 'state' => ['required', 'string']]);
        try {
            $state = json_decode(Crypt::decryptString($data['state']), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(403, 'The Google authorization request is invalid.');
        }
        abort_unless(data_get($state, 'user_id') === $request->user()->id, 403);
        $website = Website::query()->findOrFail(data_get($state, 'website_id'));
        $this->authorizeWebsite($request, $website);
        $this->oauth->authorize($website, $request->user(), $data['code']);

        return Redirect::route('admin.business-profile.locations', $website);
    }

    public function locations(Request $request, Website $website): View
    {
        $this->authorizeWebsite($request, $website);
        $connection = $website->businessProfileConnection()->firstOrFail();
        $locations = collect($this->client->accounts($connection))->flatMap(function (array $account) use ($connection): array {
            return collect($this->client->locations($connection, $account['name']))->map(fn (array $location): array => [...$location, 'accountName' => $account['name']])->all();
        });

        return view('admin.websites.business-profile-locations', compact('website', 'locations'));
    }

    public function storeLocation(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $data = $request->validate(['account_name' => ['required', 'string', 'starts_with:accounts/'], 'location_name' => ['required', 'string', 'starts_with:locations,accounts/'], 'location_title' => ['required', 'string', 'max:255']]);
        $connection = $website->businessProfileConnection()->firstOrFail();
        $available = collect($this->client->locations($connection, $data['account_name']))->firstWhere('name', $data['location_name']);
        abort_unless($available && ($available['title'] ?? null) === $data['location_title'], 422, 'That location is not available to this Google account.');
        $connection->update($data);
        $audit = $connection->audits()->create(['status' => BusinessProfileAudit::STATUS_PENDING]);
        AuditBusinessProfile::dispatch($audit);

        return Redirect::route('admin.websites.show', $website)->with('status', 'Google Business Profile connected and its first audit queued.');
    }

    public function update(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $data = $request->validate(['weekly_audits_enabled' => ['required', 'boolean'], 'weekly_posts_enabled' => ['required', 'boolean'], 'post_weekday' => ['required', 'integer', 'between:0,6'], 'post_hour' => ['required', 'integer', 'between:0,23'], 'timezone' => ['required', 'timezone'], 'brand_guidance' => ['nullable', 'string', 'max:20000']]);
        $website->businessProfileConnection()->firstOrFail()->update($data);

        return back()->with('status', 'Business Profile automation settings updated.');
    }

    public function audit(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $connection = $website->businessProfileConnection()->firstOrFail();
        $audit = $connection->audits()->whereIn('status', [BusinessProfileAudit::STATUS_PENDING, BusinessProfileAudit::STATUS_RUNNING])->first() ?: $connection->audits()->create(['status' => BusinessProfileAudit::STATUS_PENDING]);
        if ($audit->wasRecentlyCreated) {
            AuditBusinessProfile::dispatch($audit);
        }

        return back()->with('status', 'Business Profile audit queued.');
    }

    public function syncReviews(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $connection = $website->businessProfileConnection()->firstOrFail();
        $this->client->syncReviews($connection);
        $connection->reviews()->where('reply_status', BusinessProfileReview::STATUS_UNANSWERED)->each(function (BusinessProfileReview $review): void {
            $review->update(['reply_status' => BusinessProfileReview::STATUS_GENERATING]);
            GenerateBusinessProfileReviewReply::dispatch($review);
        });

        return back()->with('status', 'Reviews synced and unanswered review drafts queued.');
    }

    public function destroy(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $website->businessProfileConnection()->delete();

        return back()->with('status', 'Google Business Profile disconnected.');
    }

    protected function authorizeWebsite(Request $request, Website $website): void
    {
        abort_unless($website->isManageableBy($request->user()), 403);
    }
}
