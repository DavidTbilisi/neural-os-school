<?php

namespace App\Services\Meter;

use App\Models\Course;
use App\Models\Gym;
use App\Models\MeterEvent;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * METER's Evaluate + Report functions for one learner. Reads the unified event
 * log and produces a private dashboard view-model: a Daily Glance, per-gym
 * performance verdicts (accuracy + median latency vs floor/working/target, with
 * the N<10 "insufficient signal" guard), and course progress.
 *
 * Deliberately no gamification (no points/streaks/leaderboards) and no point
 * estimates without their N — trends and ranges only, per the METER spec.
 */
class Report
{
    /** Minimum reps before a metric is trusted (METER "insufficient signal"). */
    public const MIN_SIGNAL = 10;

    public function __construct(
        private User $user,
        private int $windowDays = 30,
    ) {}

    public function build(): array
    {
        $events = MeterEvent::forUser($this->user->id)
            ->withinDays($this->windowDays)
            ->orderBy('occurred_at')
            ->get();

        return [
            'windowDays' => $this->windowDays,
            'hasData' => $events->isNotEmpty(),
            'glance' => $this->glance($events),
            'performance' => $this->performance($events),
            'retrieval' => $this->retrieval($events),
            'totals' => $this->totals($events),
        ];
    }

    /** Today's one-line-per-layer signal. */
    private function glance(Collection $events): array
    {
        $today = $events->filter(fn (MeterEvent $e) => $e->occurred_at->isToday());
        $reps = $today->where('operation', MeterEvent::OP_GYM_REP);

        return [
            'reps' => $reps->count(),
            'accuracy' => $reps->isNotEmpty() ? $reps->avg(fn ($e) => $e->correct ? 1 : 0) : null,
            'sessions' => $today->where('operation', MeterEvent::OP_GYM_SESSION)->count(),
            'lessons' => $today->where('operation', MeterEvent::OP_LESSON_COMPLETE)->count(),
        ];
    }

    /** Per-gym recognition performance with a floor/working/target verdict. */
    private function performance(Collection $events): array
    {
        $repsByGym = $events->where('operation', MeterEvent::OP_GYM_REP)
            ->whereNotNull('mode')->groupBy('mode');

        if ($repsByGym->isEmpty()) {
            return [];
        }

        $gyms = Gym::whereIn('slug', $repsByGym->keys())->get()->keyBy('slug');
        $sessions = $events->where('operation', MeterEvent::OP_GYM_SESSION)->groupBy('mode');

        return $repsByGym->map(function (Collection $reps, string $slug) use ($gyms, $sessions) {
            $gym = $gyms->get($slug);
            $n = $reps->count();
            $accuracy = $reps->avg(fn (MeterEvent $e) => $e->correct ? 1 : 0);
            $median = Gym::median($reps->pluck('latency_ms'));

            $target = $gym?->promote_accuracy ?? 0.85;
            $working = $gym?->pass_accuracy ?? 0.80;
            $floor = max(0.0, $working - 0.20);

            $sessionAcc = ($sessions->get($slug) ?? collect())
                ->sortBy('occurred_at')->pluck('value')->filter(fn ($v) => $v !== null);

            return [
                'title' => $gym?->title ?? $slug,
                'slug' => $slug,
                'n' => $n,
                'insufficient' => $n < self::MIN_SIGNAL,
                'accuracy' => $accuracy,
                'medianLatencyMs' => $median,
                'target' => $target,
                'floor' => $floor,
                'verdict' => $this->verdict($n, $accuracy, $target, $working, $floor),
                'latencyRead' => $this->latencyRead($median, $gym?->latency_target_ms),
                'stage' => optional($sessions->get($slug)?->sortBy('occurred_at')->last())->context['stage_code'] ?? null,
                'trend' => $sessionAcc->map(fn ($v) => (int) round($v * 100))->values()->all(),
                'breach' => $this->floorBreach($sessionAcc, $floor),
            ];
        })->values()->all();
    }

    /** ['key'=>, 'label'=>, 'tone'=>] — the evaluation verdict. */
    private function verdict(int $n, float $accuracy, float $target, float $working, float $floor): array
    {
        if ($n < self::MIN_SIGNAL) {
            return ['key' => 'insufficient', 'label' => 'Insufficient signal', 'tone' => 'gray'];
        }
        if ($accuracy >= $target) {
            return ['key' => 'target', 'label' => 'Promote-ready', 'tone' => 'emerald'];
        }
        if ($accuracy >= $working) {
            return ['key' => 'working', 'label' => 'On track', 'tone' => 'indigo'];
        }
        if ($accuracy >= $floor) {
            return ['key' => 'below', 'label' => 'Below target', 'tone' => 'amber'];
        }

        return ['key' => 'floor', 'label' => 'Needs attention', 'tone' => 'rose'];
    }

    private function latencyRead(?int $median, ?int $targetMs): ?string
    {
        if ($median === null || ! $targetMs) {
            return null;
        }
        if ($median <= $targetMs) {
            return 'fast';
        }

        return $median <= $targetMs * 1.5 ? 'near' : 'slow';
    }

    /** METER: floor breach = the last 3 sessions all below floor. */
    private function floorBreach(Collection $sessionAcc, float $floor): bool
    {
        $last3 = $sessionAcc->take(-3);

        return $last3->count() >= 3 && $last3->every(fn ($v) => $v < $floor);
    }

    private function retrieval(Collection $events): array
    {
        $lessons = $events->where('operation', MeterEvent::OP_LESSON_COMPLETE);

        $courses = $this->user->enrollments()->with('course.modules.lessons')->get()
            ->map(function ($enrollment) {
                $course = $enrollment->course;
                $required = $course->requiredLessons();
                $done = $required->filter(fn ($l) => $l->completedBy($this->user))->count();

                return [
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'progress' => $required->isNotEmpty() ? $done / $required->count() : 0.0,
                    'done' => $done,
                    'total' => $required->count(),
                    'complete' => $enrollment->isComplete(),
                ];
            });

        return [
            'lessonsInWindow' => $lessons->count(),
            'lessonsThisWeek' => $lessons->filter(fn (MeterEvent $e) => $e->occurred_at->greaterThanOrEqualTo(now()->subDays(7)))->count(),
            'courses' => $courses->all(),
        ];
    }

    private function totals(Collection $events): array
    {
        return [
            'reps' => $events->where('operation', MeterEvent::OP_GYM_REP)->count(),
            'sessions' => $events->where('operation', MeterEvent::OP_GYM_SESSION)->count(),
            'activeDays' => $events->map(fn (MeterEvent $e) => $e->occurred_at->toDateString())->unique()->count(),
        ];
    }
}
