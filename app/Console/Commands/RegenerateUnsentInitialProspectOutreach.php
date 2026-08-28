<?php

namespace App\Console\Commands;

use App\Enums\ProspectOutreachMessageType;
use App\Models\Prospect;
use App\Services\InitialProspectOutreachGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('outreach:regenerate-unsent-initial {--dry-run : Show what would change without updating records}')]
#[Description('Regenerate unsent initial prospect outreach using the conversational cold-email strategy')]
class RegenerateUnsentInitialProspectOutreach extends Command
{
    public function handle(InitialProspectOutreachGenerator $generator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $found = 0;
        $regenerated = 0;
        $skipped = [];

        Prospect::query()
            ->whereNull('sent_at')
            ->whereNotNull('outreach_subject')
            ->whereNotNull('outreach_body')
            ->with(['prospectingLocation'])
            ->orderBy('id')
            ->lazyById(200)
            ->each(function (Prospect $prospect) use ($generator, $dryRun, &$found, &$regenerated, &$skipped): void {
                $found++;

                if ($prospect->outreachDeliveries()
                    ->where('message_type', ProspectOutreachMessageType::Initial)
                    ->whereNotNull('sent_at')
                    ->exists()) {
                    $skipped['sent initial delivery exists'] = ($skipped['sent initial delivery exists'] ?? 0) + 1;

                    return;
                }

                $draft = $generator->generate($prospect);

                if ($draft === null) {
                    $skipped['missing verified keyword or ranking position'] = ($skipped['missing verified keyword or ranking position'] ?? 0) + 1;

                    return;
                }

                if ($prospect->outreach_subject === $draft['subject'] && $prospect->outreach_body === $draft['body']) {
                    $skipped['already uses current generated copy'] = ($skipped['already uses current generated copy'] ?? 0) + 1;

                    return;
                }

                if (! $dryRun) {
                    DB::transaction(function () use ($prospect, $draft): void {
                        $lockedProspect = Prospect::query()->lockForUpdate()->findOrFail($prospect->id);

                        if ($lockedProspect->sent_at !== null) {
                            return;
                        }

                        $lockedProspect->update([
                            'outreach_subject' => $draft['subject'],
                            'outreach_body' => $draft['body'],
                        ]);
                        $lockedProspect->outreachDeliveries()
                            ->where('message_type', ProspectOutreachMessageType::Initial)
                            ->whereNull('sent_at')
                            ->whereIn('status', ['pending', 'scheduled'])
                            ->update([
                                'subject' => $draft['subject'],
                                'body' => $draft['body'],
                            ]);
                    });
                }

                $regenerated++;
            });

        $this->newLine();
        $this->components->twoColumnDetail('Mode', $dryRun ? 'dry run' : 'updated records');
        $this->components->twoColumnDetail('Unsent initial emails found', (string) $found);
        $this->components->twoColumnDetail($dryRun ? 'Would regenerate' : 'Regenerated', (string) $regenerated);
        $this->components->twoColumnDetail('Skipped', (string) array_sum($skipped));

        foreach ($skipped as $reason => $count) {
            $this->components->twoColumnDetail('Skipped: '.$reason, (string) $count);
        }

        $this->info('Existing scheduling and tracking identifiers were preserved. No sent email was modified and no email was dispatched.');

        return self::SUCCESS;
    }
}
