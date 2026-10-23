<?php

namespace App\Console\Commands;

use App\Models\WebsiteDomain;
use App\Models\WebsiteLocalProject;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('agent:link-project {domain : Exact verified Sitewell domain} {path? : Absolute path on the named Codex host} {--project-id= : Codex project ID from the app project list} {--host=local : Host owning the checkout} {--remove : Remove this host mapping only}')]
#[Description('Link a verified Sitewell website to local Codex project metadata; never executes or opens the supplied path')]
class LinkAgentProject extends Command
{
    public function handle(): int
    {
        $domain = strtolower(rtrim((string) $this->argument('domain'), '.'));
        $domains = WebsiteDomain::query()->where('domain', $domain)->where('ownership_status', 'verified')->get(['website_id']);
        if ($domains->count() !== 1) {
            $this->error('Use an exact verified domain belonging to one Sitewell website.');

            return self::FAILURE;
        }
        $data = ['host' => $this->option('host'), 'path' => $this->argument('path'), 'codex_project_id' => $this->option('project-id')];
        $validator = Validator::make($data, [
            'host' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'path' => [$this->option('remove') ? 'nullable' : 'required', 'string', 'max:1000', 'regex:~^/[^\x00-\x1f\x7f]*$~', 'not_regex:~(?:^|/)\.{1,2}(?:/|$)~'],
            'codex_project_id' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_-]+$/'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $key = ['website_id' => $domains->sole()->website_id, 'host' => $data['host']];
        if ($this->option('remove')) {
            WebsiteLocalProject::query()->where($key)->delete();
            $this->info('Local project link removed.');
        } else {
            WebsiteLocalProject::query()->updateOrCreate($key, ['path' => rtrim($data['path'], '/') ?: '/', 'codex_project_id' => $data['codex_project_id']]);
            $this->info('Local project linked. Verify the host, checkout and domain before making changes; this mapping grants no execution authority.');
        }

        return self::SUCCESS;
    }
}
