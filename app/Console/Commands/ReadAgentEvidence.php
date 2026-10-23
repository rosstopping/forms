<?php

namespace App\Console\Commands;

use App\Services\AgentSeoEvidence;
use App\Services\SitewellAgentClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('agent:evidence {website? : Sitewell website ID; omit to list/search accessible sites and project links} {section? : Evidence section from the website catalogue} {--search= : Website name/domain search} {--keyword= : Literal phrase filter where supported} {--from= : YYYY-MM-DD} {--to= : YYYY-MM-DD} {--page=1} {--per-page=25} {--parent-id= : Scope to one report, snapshot, audit or impact} {--location-code=} {--language-code=} {--device=}')]
#[Description('Read stored SEO evidence and Codex project links through the configured Sitewell API')]
class ReadAgentEvidence extends Command
{
    public function handle(SitewellAgentClient $client): int
    {
        $website = $this->argument('website');
        $section = $this->argument('section');
        if (($website !== null && ! preg_match('/^[1-9][0-9]*$/D', $website)) || ($section !== null && (! $website || ! in_array($section, AgentSeoEvidence::SECTIONS, true)))) {
            $this->error('Choose a numeric website ID and an evidence section listed by the website catalogue.');

            return self::FAILURE;
        }
        $query = [];
        foreach (['search', 'keyword', 'from', 'to', 'page', 'per-page', 'parent-id', 'location-code', 'language-code', 'device'] as $option) {
            if ($this->option($option) !== null && $this->option($option) !== '') {
                $query[str_replace('-', '_', $option)] = $this->option($option);
            }
        }
        $path = 'websites'.($website ? '/'.$website : '').($section ? '/evidence/'.$section : '');
        try {
            $data = $client->get($path, $query);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
