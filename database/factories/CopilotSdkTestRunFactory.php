<?php

namespace Database\Factories;

use App\Models\CopilotSdkTestRun;
use App\Models\User;
use App\Models\WebsiteRepository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CopilotSdkTestRun> */
class CopilotSdkTestRunFactory extends Factory
{
    public function definition(): array
    {
        $id = (string) Str::uuid();

        return [
            'run_id' => $id, 'website_repository_id' => WebsiteRepository::factory(),
            'requested_by' => User::factory()->state(['role' => User::ROLE_ADMIN]),
            'repository_id' => 123, 'installation_id' => 456, 'full_name' => 'sitewellross/test',
            'base_branch' => 'main', 'base_sha' => str_repeat('a', 40), 'tree_sha' => str_repeat('b', 40),
            'path' => 'index.html', 'title' => 'Sitewell SDK test', 'original' => '<title>Home</title>',
            'branch' => 'sitewell/sdk-test-'.$id, 'status' => 'running',
        ];
    }
}
