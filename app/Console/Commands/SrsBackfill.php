<?php

namespace App\Console\Commands;

use App\Models\GymAttempt;
use App\Models\SrsCard;
use App\Support\Srs;
use Illuminate\Console\Command;

/**
 * Rebuild every learner's SRS card schedule by replaying all gym attempts in
 * insert order (id order == chronological for autoincrement rows). Run once
 * after deploying SRS so history collected before the scheduler existed
 * produces realistic card state. Rebuild-from-scratch, so re-running always
 * converges to the same result.
 *
 *   ./run php artisan srs:backfill
 */
class SrsBackfill extends Command
{
    protected $signature = 'srs:backfill';

    protected $description = 'Rebuild SRS card schedules by replaying gym attempts chronologically.';

    public function handle(): int
    {
        SrsCard::query()->delete();

        $replayed = 0;
        GymAttempt::whereNotNull('gym_item_id')
            ->with('session', 'item')
            ->chunkById(500, function ($chunk) use (&$replayed) {
                foreach ($chunk as $attempt) {
                    if (! $attempt->session || ! $attempt->item) {
                        continue;
                    }
                    Srs::record(
                        $attempt->session->user_id,
                        $attempt->item,
                        $attempt->is_correct,
                        $attempt->created_at,
                    );
                    $replayed++;
                }
            });

        $this->info("SRS backfill: replayed {$replayed} attempts into ".SrsCard::count().' cards.');

        return self::SUCCESS;
    }
}
