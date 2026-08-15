<?php

namespace App\Console\Commands;

use App\Jobs\GenerateFreeSiteAudit;
use App\Models\Prospect;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('free-site-audits:dispatch-pending-emails')]
#[Description('Retry completed free site audits whose results email has not been sent')]
class DispatchPendingFreeSiteAuditEmails extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dispatched = 0;

        Prospect::query()
            ->where('analysis_status', 'completed')
            ->whereNotNull('email')
            ->whereHas('activities', fn ($query) => $query->where('type', 'free_audit_requested'))
            ->whereDoesntHave('activities', fn ($query) => $query->whereIn('type', ['free_audit_email_sent', 'free_audit_email_failed']))
            ->chunkById(100, function ($prospects) use (&$dispatched): void {
                foreach ($prospects as $prospect) {
                    GenerateFreeSiteAudit::dispatch($prospect);
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} pending free site audit email(s).");

        return self::SUCCESS;
    }
}
