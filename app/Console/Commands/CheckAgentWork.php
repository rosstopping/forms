<?php

namespace App\Console\Commands;

use App\Services\SitewellAgentClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('agent:check')]
#[Description('Read the configured Sitewell agent work API without changing any work')]
class CheckAgentWork extends Command
{
    public function handle(SitewellAgentClient $client): int
    {
        try {
            $data = $client->get('work');
            if (! is_array($data['summary'] ?? null)) {
                throw new RuntimeException('Sitewell agent API did not return a valid work summary.');
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
