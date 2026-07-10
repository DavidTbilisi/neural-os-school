<?php

namespace App\Console\Commands;

use App\Models\GymAttempt;
use App\Models\GymSession;
use App\Models\LessonCompletion;
use App\Models\MeterEvent;
use App\Support\Meter;
use Illuminate\Console\Command;

/**
 * Replay existing gym + course telemetry into the METER event log. Idempotent —
 * every event is upserted on its source_key, so re-running never duplicates.
 * Run once after deploying METER to seed the log from history collected before
 * live emit existed.
 *
 *   ./run php artisan meter:backfill
 */
class MeterBackfill extends Command
{
    protected $signature = 'meter:backfill';

    protected $description = 'Replay gym + course telemetry into the METER event log (idempotent).';

    public function handle(): int
    {
        $reps = 0;
        GymAttempt::with('session.gym')->chunkById(500, function ($chunk) use (&$reps) {
            foreach ($chunk as $attempt) {
                if ($attempt->session) {
                    Meter::gymRep($attempt);
                    $reps++;
                }
            }
        });

        $sessions = 0;
        GymSession::whereNotNull('completed_at')->with('gym')->chunkById(500, function ($chunk) use (&$sessions) {
            foreach ($chunk as $session) {
                Meter::gymSession($session);
                $sessions++;
            }
        });

        $lessons = 0;
        LessonCompletion::with('lesson.module')->chunkById(500, function ($chunk) use (&$lessons) {
            foreach ($chunk as $completion) {
                Meter::lessonComplete($completion);
                $lessons++;
            }
        });

        $this->info("Backfilled: {$reps} gym reps · {$sessions} gym sessions · {$lessons} lesson completions.");
        $this->line('  meter_events total: '.MeterEvent::count());

        return self::SUCCESS;
    }
}
