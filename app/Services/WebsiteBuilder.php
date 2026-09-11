<?php

namespace App\Services;

use App\Models\GithubInstallation;
use App\Models\GithubUserAuthorization;
use App\Models\User;
use App\Models\Website;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WebsiteBuilder
{
    public function __construct(
        private StaticWebsiteGenerator $generator,
        private GithubOAuthClient $github,
        private NetlifyClient $netlify,
        private CopilotAgentClient $copilot,
    ) {}

    /** @param array{name: string, sector: string, description: string, pages: array<int, string>, repository_name: string, github_installation_id: int, user_id?: int|null} $details */
    public function build(array $details, User $creator): Website
    {
        $authorization = GithubUserAuthorization::query()->whereBelongsTo($creator)->first();

        if (! $authorization) {
            throw new RuntimeException('Connect your GitHub account before building a website.');
        }

        $installation = GithubInstallation::query()
            ->where('status', GithubInstallation::STATUS_ACTIVE)
            ->where('repository_selection', 'all')
            ->findOrFail($details['github_installation_id']);
        try {
            $installation = $this->github->refreshInstallation($authorization, $installation);
        } catch (DecryptException) {
            throw new RuntimeException('Your stored GitHub authorization can no longer be decrypted. Use Reconnect GitHub on the Website Builder, then try again.');
        }

        if ($installation->status !== GithubInstallation::STATUS_ACTIVE
            || $installation->repository_selection !== 'all'
            || ($installation->permissions['administration'] ?? null) !== 'write'
            || ($installation->permissions['contents'] ?? null) !== 'write') {
            throw new RuntimeException('The Sitewell GitHub App needs Repository permissions → Administration and Contents both set to Read and write. Update the GitHub App, approve the new permissions for this installation, then try again.');
        }

        $files = $this->generator->generate($details);
        $repository = $this->github->createRepository($authorization, $installation->account_login, $details['repository_name'], $files);
        $site = $this->netlify->deployRepository($repository);

        $website = DB::transaction(function () use ($details, $installation, $repository, $site): Website {
            $website = Website::query()->create([
                'name' => $details['name'],
                'user_id' => $details['user_id'] ?? null,
                'is_active' => true,
                'auto_discovered' => false,
                'email_enabled' => true,
                'email_recipients' => [config('forms.default_recipient')],
                'webhook_enabled' => false,
                'health_reports_enabled' => false,
            ]);
            if ($website->user_id) {
                $website->members()->attach($website->user_id, ['role' => Website::MEMBER_ROLE_MANAGER]);
            }
            $website->domains()->create(['domain' => $site['domain'], 'is_primary' => true]);
            $website->forms()->create(['name' => 'Contact form', 'slug' => 'contact-form', 'is_active' => true, 'auto_discovered' => false]);
            $website->repository()->create([
                'github_installation_id' => $installation->id,
                'repository_id' => $repository['id'],
                'full_name' => $repository['full_name'],
                'default_branch' => $repository['default_branch'] ?? 'main',
                'private' => $repository['private'] ?? false,
                'permissions' => $repository['permissions'] ?? [],
            ]);

            return $website;
        });

        $prompt = $this->generator->prompt($details);
        $task = $this->copilot->startTask($authorization, $website->repository, $prompt);
        $website->update([
            'copilot_build_task_id' => $task['id'],
            'copilot_build_task_url' => $task['html_url'] ?? null,
            'copilot_build_task_state' => $task['state'] ?? 'queued',
            'copilot_build_prompt' => $prompt,
        ]);

        return $website;
    }
}
