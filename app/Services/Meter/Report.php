<?php

namespace App\Services\Meter;

use App\Models\Course;
use App\Models\Gym;
use App\Models\MeterEvent;
use App\Models\Module;
use App\Models\SrsCard;
use App\Models\User;
use App\Support\KnowledgeLadder;
use Illuminate\Support\Collection;

/**
 * METER's Evaluate + Report functions for one learner. Reads the unified event
 * log and produces a private dashboard view-model: a Daily Glance, per-gym
 * performance verdicts (accuracy + median latency vs floor/working/target, with
 * the N<10 "insufficient signal" guard), course progress, and per-module
 * coverage evidence (read straight from the gym-attempt stream, where module
 * attribution lives on the tagged items).
 *
 * Deliberately no gamification (no points/streaks/leaderboards) and no point
 * estimates without their N — trends and ranges only, per the METER spec.
 */
class Report
{
    /** Minimum reps before a metric is trusted (METER "insufficient signal"). */
    public const MIN_SIGNAL = 10;

    /** Minimum distinct sessions before coverage counts as sustained (not one lucky run). */
    public const MIN_SESSIONS = 2;

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
            'coverage' => $this->coverage(),
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
            'reviewsDue' => SrsCard::where('user_id', $this->user->id)->due()->count(),
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
                'verdict' => self::verdict($n, $accuracy, $target, $working, $floor),
                'latencyRead' => $this->latencyRead($median, $gym?->latency_target_ms),
                'stage' => optional($sessions->get($slug)?->sortBy('occurred_at')->last())->context['stage_code'] ?? null,
                'trend' => $sessionAcc->map(fn ($v) => (int) round($v * 100))->values()->all(),
                'breach' => $this->floorBreach($sessionAcc, $floor),
            ];
        })->values()->all();
    }

    /** ['key'=>, 'label'=>, 'tone'=>] — the evaluation verdict. */
    private static function verdict(int $n, float $accuracy, float $target, float $working, float $floor): array
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

    /**
     * Per-module coverage evidence for each enrolled course, read from the
     * gym-attempt stream via the module-tagged items (Module::gymAttempts()).
     * A module reads as COVERED when the claim survives all three guards:
     * n ≥ MIN_SIGNAL reps in the window, across ≥ MIN_SESSIONS sessions,
     * at rolling accuracy ≥ the instrument gym's pass_accuracy — i.e. rung 4
     * (Classifiable) or better on the Knowledge Ladder, sustained.
     *
     * Modules with no tagged items have no instrument and get no verdict —
     * they are counted as `uninstrumented`, never silently marked covered.
     */
    private function coverage(): array
    {
        $courses = $this->user->enrollments()
            ->with('course.modules.gymItems.gym')->get()
            ->map(fn ($enrollment) => $enrollment->course);

        return $courses
            ->filter(fn (Course $course) => $course->modules->contains(fn (Module $m) => $m->gymItems->isNotEmpty()))
            ->map(fn (Course $course) => [
                'title' => $course->title,
                'slug' => $course->slug,
                // Where to send the learner for a retention pass.
                'reviewGym' => $course->modules->flatMap->gymItems->first()?->gym?->slug,
                'uninstrumented' => $course->modules->filter(fn (Module $m) => $m->gymItems->isEmpty())->count(),
                'modules' => $course->modules
                    ->filter(fn (Module $m) => $m->gymItems->isNotEmpty())
                    ->map(fn (Module $m) => self::moduleEvidence($m, $this->user, $this->windowDays))
                    ->values()->all(),
            ])
            ->values()->all();
    }

    /**
     * The evidence read for one instrumented module — the canonical coverage
     * primitive. Public/static because the course layer reads it too:
     * Module::completedBy() gates module (and thus course) completion on
     * `covered`, so the dashboard and the gate can never disagree.
     */
    public static function moduleEvidence(Module $module, User $user, int $windowDays = 30): array
    {
        $attempts = $module->gymAttempts()
            ->whereHas('session', fn ($q) => $q->where('user_id', $user->id))
            ->where('gym_attempts.created_at', '>=', now()->subDays($windowDays))
            ->get();

        $n = $attempts->count();
        $sessions = $attempts->pluck('gym_session_id')->unique()->count();
        $accuracy = $n > 0 ? $attempts->avg(fn ($a) => $a->is_correct ? 1 : 0) : null;
        $median = Gym::median($attempts->pluck('latency_ms'));

        // The instrument: the gym owning most of the module's tagged items.
        $gymId = $module->gymItems->countBy('gym_id')->sortDesc()->keys()->first();
        $gym = $module->gymItems->firstWhere('gym_id', $gymId)?->gym;

        $target = $gym?->promote_accuracy ?? 0.85;
        $working = $gym?->pass_accuracy ?? 0.80;
        $floor = max(0.0, $working - 0.20);

        $insufficient = $n < self::MIN_SIGNAL;
        $sustained = $sessions >= self::MIN_SESSIONS;

        // The bar: evidence must reach the module's target rung. The achieved
        // rung comes from the same accuracy+latency mapping every gym session
        // uses; at the default target (4 Classifiable) this is exactly the old
        // accuracy ≥ pass rule. Targets above the gym ceiling (7) can't be
        // certified by a timed recognition drill — `certifiable` goes false
        // rather than the gate pretending more reps could ever close it.
        $targetRung = (int) ($module->target_rung ?? KnowledgeLadder::DEFAULT_TARGET);
        $achieved = (! $insufficient && $gym)
            ? KnowledgeLadder::levelForGym($gym, $accuracy, $median)
            : null;

        return [
            'title' => $module->title,
            'n' => $n,
            'sessions' => $sessions,
            'accuracy' => $accuracy,
            'medianLatencyMs' => $median,
            'target' => $target,
            'pass' => $working,
            'insufficient' => $insufficient,
            'sustained' => $sustained,
            'verdict' => self::verdict($n, $accuracy ?? 0.0, $target, $working, $floor),
            'rung' => $achieved !== null ? KnowledgeLadder::rung($achieved) : null,
            'targetRung' => KnowledgeLadder::rung($targetRung),
            'certifiable' => $targetRung <= KnowledgeLadder::GYM_CEILING,
            'covered' => $achieved !== null && $sustained && $achieved >= $targetRung,
            'retention' => self::retention($module, $user),
        ];
    }

    /**
     * Retention state for a module: of the learner's scheduled SRS cards on
     * its items, how many are current vs due? Deliberately separate from
     * `covered` — retention decay surfaces as due reviews, never as a
     * silently reopened completion gate.
     */
    private static function retention(Module $module, User $user): array
    {
        $cards = SrsCard::where('user_id', $user->id)
            ->whereIn('gym_item_id', $module->gymItems->pluck('id'))
            ->get();

        $due = $cards->filter(fn (SrsCard $c) => $c->due_at->lessThanOrEqualTo(now()))->count();

        return [
            'scheduled' => $cards->count(),
            'due' => $due,
            'rate' => $cards->isNotEmpty() ? ($cards->count() - $due) / $cards->count() : null,
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
