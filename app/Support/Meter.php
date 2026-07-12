<?php

namespace App\Support;

use App\Models\GymAttempt;
use App\Models\GymSession;
use App\Models\LessonCompletion;
use App\Models\MeterEvent;

/**
 * The METER "Emit" surface. Every signal-producing operation calls one of these
 * to append a row to the unified meter_events log. Each event carries a stable
 * `source_key` so the same underlying record only ever produces one event — live
 * emit and `meter:backfill` share these methods and stay idempotent.
 */
class Meter
{
    /** Upsert an event keyed on source_key (idempotent); insert if no key. */
    public static function emit(array $attrs): MeterEvent
    {
        if (! empty($attrs['source_key'])) {
            $key = $attrs['source_key'];
            unset($attrs['source_key']);

            return MeterEvent::updateOrCreate(['source_key' => $key], $attrs);
        }

        return MeterEvent::create($attrs);
    }

    /** One gym rep: hit/miss + latency (Performance layer). */
    public static function gymRep(GymAttempt $attempt): MeterEvent
    {
        $attempt->loadMissing('session.gym');
        $session = $attempt->session;
        $gym = $session?->gym;

        return self::emit([
            'user_id' => $session->user_id,
            'occurred_at' => $attempt->created_at ?? now(),
            'layer' => MeterEvent::LAYER_PERFORMANCE,
            'operation' => MeterEvent::OP_GYM_REP,
            'metric_type' => 'hit-miss',
            'mode' => $gym?->slug,
            'correct' => $attempt->is_correct,
            'latency_ms' => $attempt->latency_ms,
            'value' => $attempt->is_correct ? 1.0 : 0.0,
            'context' => [
                'gym_id' => $session->gym_id,
                'gym_session_id' => $session->id,
                'gym_item_id' => $attempt->gym_item_id,
                'selected' => $attempt->selected,
            ],
            'source_key' => 'gym_attempt:'.$attempt->id,
        ]);
    }

    /** The summary of a finished gym session (accuracy, median latency, stage). */
    public static function gymSession(GymSession $session): MeterEvent
    {
        $session->loadMissing('gym');

        return self::emit([
            'user_id' => $session->user_id,
            'occurred_at' => $session->completed_at ?? now(),
            'layer' => MeterEvent::LAYER_PERFORMANCE,
            'operation' => MeterEvent::OP_GYM_SESSION,
            'metric_type' => 'session-summary',
            'mode' => $session->gym?->slug,
            'correct' => null,
            'latency_ms' => $session->median_latency_ms,
            'value' => $session->accuracy,
            'context' => [
                'gym_id' => $session->gym_id,
                'gym_session_id' => $session->id,
                'total' => $session->total,
                'correct' => $session->correct,
                'stage_code' => $session->stage_code,
            ],
            'source_key' => 'gym_session:'.$session->id,
        ]);
    }

    /** One SRS review rep: a due card re-tested (Retrieval layer — memory, not first-pass performance). */
    public static function srsReview(GymAttempt $attempt): MeterEvent
    {
        $attempt->loadMissing('session.gym', 'item');
        $session = $attempt->session;

        return self::emit([
            'user_id' => $session->user_id,
            'occurred_at' => $attempt->created_at ?? now(),
            'layer' => MeterEvent::LAYER_RETRIEVAL,
            'operation' => MeterEvent::OP_SRS_REVIEW,
            'metric_type' => 'hit-miss',
            'mode' => $session->gym?->slug,
            'correct' => $attempt->is_correct,
            'latency_ms' => $attempt->latency_ms,
            'value' => $attempt->is_correct ? 1.0 : 0.0,
            'context' => [
                'gym_item_id' => $attempt->gym_item_id,
                'module_id' => $attempt->item?->module_id,
            ],
            'source_key' => 'srs_review:'.$attempt->id,
        ]);
    }

    /** A lesson marked complete (Retrieval layer). Deduped per (user, lesson). */
    public static function lessonComplete(LessonCompletion $completion): MeterEvent
    {
        $completion->loadMissing('lesson.module');
        $lesson = $completion->lesson;

        return self::emit([
            'user_id' => $completion->user_id,
            'occurred_at' => $completion->completed_at ?? $completion->created_at ?? now(),
            'layer' => MeterEvent::LAYER_RETRIEVAL,
            'operation' => MeterEvent::OP_LESSON_COMPLETE,
            'metric_type' => 'completion',
            'mode' => null,
            'value' => 1.0,
            'context' => [
                'lesson_id' => $lesson?->id,
                'module_id' => $lesson?->module_id,
                'course_id' => $lesson?->module?->course_id,
            ],
            'source_key' => 'lesson:'.$completion->user_id.':'.$completion->lesson_id,
        ]);
    }
}
