<?php

namespace App\Support;

use App\Models\Gym;
use App\Models\GymAttempt;
use App\Models\GymItem;
use App\Models\GymSession;
use Illuminate\Support\Carbon;

/**
 * The one true "record a gym round" / "close a gym session" implementation.
 * PlayGym's full drill/review flow and the embedded LessonCheck both call
 * these, so there is exactly one place that writes GymAttempt rows, emits
 * METER events, and reschedules SRS cards — never two competing scorers.
 */
final class GymScoring
{
    public static function record(GymSession $session, GymItem $item, ?string $choice, int $latencyMs, bool $isReview = false): GymAttempt
    {
        $isCorrect = $item->isCorrect($choice);

        $attempt = GymAttempt::create([
            'gym_session_id' => $session->id,
            'gym_item_id' => $item->id,
            'selected' => $choice,
            'is_correct' => $isCorrect,
            'latency_ms' => max(0, $latencyMs),
        ]);

        Meter::gymRep($attempt); // emit the METER rep event

        // Every exposure reschedules the item's retention card; a review rep
        // additionally lands in the Retrieval layer of the event log.
        Srs::record((int) $session->user_id, $item, $isCorrect);
        if ($isReview) {
            Meter::srsReview($attempt);
        }

        $session->update([
            'total' => $session->total + 1,
            'correct' => $session->correct + ($isCorrect ? 1 : 0),
        ]);

        return $attempt;
    }

    /** Compute + persist the session summary (accuracy, median latency, stage). */
    public static function finalizeSession(GymSession $session, Gym $gym): void
    {
        $attempts = $session->attempts()->get();

        $total = $attempts->count();
        $correct = $attempts->where('is_correct', true)->count();
        $accuracy = $total ? $correct / $total : 0.0;
        $median = Gym::median($attempts->pluck('latency_ms'));

        $session->update([
            'completed_at' => Carbon::now(),
            'total' => $total,
            'correct' => $correct,
            'accuracy' => $accuracy,
            'median_latency_ms' => $median,
            // Store the Red Queen Knowledge Ladder rung (0–9) as "L{n}".
            'stage_code' => 'L'.$gym->knowledgeLevelFor($accuracy, $median),
        ]);

        Meter::gymSession($session); // emit the METER session-summary event
    }
}
