<?php

namespace App\Console\Commands;

use App\Jobs\GenerateWebsiteHealthReport;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use Illuminate\Console\Command;

class DispatchDueWebsiteHealthReports extends Command
{
    protected $signature = 'health-reports:dispatch';

    protected $description = 'Dispatch due weekly website health reports';

    public function handle(): int
    {
        $dispatched = 0;
        $frequencyDays = config('forms.health_reports.frequency_days');

        Website::query()
            ->where('is_active', true)
            ->where('health_reports_enabled', true)
            ->whereDoesntHave('healthReports', fn ($query) => $query
                ->whereIn('status', [WebsiteHealthReport::STATUS_PENDING, WebsiteHealthReport::STATUS_RUNNING])
                ->orWhere(fn ($query) => $query
                    ->where('status', WebsiteHealthReport::STATUS_COMPLETED)
                    ->where('completed_at', '>=', now()->subDays($frequencyDays))))
            ->chunkById(100, function ($websites) use (&$dispatched): void {
                foreach ($websites as $website) {
                    $report = $website->healthReports()->create(['status' => WebsiteHealthReport::STATUS_PENDING]);
                    GenerateWebsiteHealthReport::dispatch($report);
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} website health reports.");

        return self::SUCCESS;
    }
}
