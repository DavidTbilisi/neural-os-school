<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\PlayGym;
use App\Models\Gym;
use App\Models\MeterEvent;
use App\Models\User;
use App\Services\Meter\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class MeterTest extends TestCase
{
    use RefreshDatabase;

    private function gym(string $slug): Gym
    {
        $gym = Gym::create([
            'slug' => $slug, 'title' => 'Test Gym', 'mode' => 'recognition',
            'timer_seconds' => 8, 'round_count' => 2, 'latency_target_ms' => 6000,
            'pass_accuracy' => 0.80, 'promote_accuracy' => 0.85, 'status' => Gym::STATUS_PUBLISHED,
            'stages' => [['code' => 'S1', 'label' => 'x', 'min_accuracy' => 0.0, 'max_latency_ms' => null]],
        ]);
        foreach (range(0, 1) as $i) {
            $gym->items()->create(['prompt' => "Q{$i}", 'choices' => ['A', 'B'], 'correct' => 'A', 'sort' => $i]);
        }

        return $gym;
    }

    /** Fabricate N gym-rep events (of which $correct are hits) directly in the log. */
    private function reps(User $u, string $slug, int $n, int $correct, int $latencyMs = 3000): void
    {
        for ($i = 0; $i < $n; $i++) {
            MeterEvent::create([
                'user_id' => $u->id, 'occurred_at' => now(),
                'layer' => MeterEvent::LAYER_PERFORMANCE, 'operation' => MeterEvent::OP_GYM_REP,
                'metric_type' => 'hit-miss', 'mode' => $slug,
                'correct' => $i < $correct, 'latency_ms' => $latencyMs, 'value' => $i < $correct ? 1 : 0,
            ]);
        }
    }

    // ---- emit -------------------------------------------------------------

    public function test_playing_a_gym_emits_rep_and_session_events(): void
    {
        $gym = $this->gym('emit-gym');
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        Livewire::actingAs($learner)->test(PlayGym::class, ['slug' => 'emit-gym'])
            ->call('start')
            ->call('answer', 'A', 1500)->call('next')
            ->call('answer', 'B', 2500)->call('next');

        $events = MeterEvent::where('user_id', $learner->id)->get();
        $this->assertSame(2, $events->where('operation', MeterEvent::OP_GYM_REP)->count());
        $this->assertSame(1, $events->where('operation', MeterEvent::OP_GYM_SESSION)->count());

        $rep = $events->firstWhere('operation', MeterEvent::OP_GYM_REP);
        $this->assertSame('emit-gym', $rep->mode);
        $this->assertSame(MeterEvent::LAYER_PERFORMANCE, $rep->layer);
        $this->assertNotNull($rep->source_key);
    }

    public function test_completing_a_lesson_emits_a_retrieval_event(): void
    {
        $course = \App\Models\Course::create(['slug' => 'c', 'title' => 'C', 'status' => 'published']);
        $module = $course->modules()->create(['title' => 'M', 'sort' => 0]);
        $page = \App\Models\Page::create(['slug' => 'p', 'title' => 'P', 'rel_path' => 'x/p.md', 'body_md' => '# P', 'visibility' => 'public']);
        $lesson = $module->lessons()->create(['page_id' => $page->id, 'title' => 'L', 'sort' => 0]);
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        Livewire::actingAs($learner)->test(\App\Livewire\ShowCourse::class, ['slug' => 'c'])
            ->call('enroll')
            ->call('toggleLesson', $lesson->id);

        $this->assertSame(1, MeterEvent::where('user_id', $learner->id)
            ->where('operation', MeterEvent::OP_LESSON_COMPLETE)->count());
    }

    // ---- backfill ---------------------------------------------------------

    public function test_backfill_is_idempotent(): void
    {
        $gym = $this->gym('bf-gym');
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $session = \App\Models\GymSession::create(['user_id' => $learner->id, 'gym_id' => $gym->id, 'started_at' => now()]);
        foreach ($gym->items as $item) {
            \App\Models\GymAttempt::create(['gym_session_id' => $session->id, 'gym_item_id' => $item->id,
                'selected' => 'A', 'is_correct' => true, 'latency_ms' => 1000]);
        }

        Artisan::call('meter:backfill');
        $first = MeterEvent::count();
        Artisan::call('meter:backfill');

        $this->assertSame(2, $first);                 // 2 attempts → 2 rep events
        $this->assertSame($first, MeterEvent::count()); // re-run does not duplicate
    }

    // ---- evaluate ---------------------------------------------------------

    public function test_verdict_reflects_accuracy_against_thresholds(): void
    {
        $this->gym('acc-gym'); // pass .80 / promote .85 / floor .60

        $target = User::factory()->create();
        $this->reps($target, 'acc-gym', 10, 9);   // 90% → promote-ready
        $this->assertSame('target', (new Report($target))->build()['performance'][0]['verdict']['key']);

        $below = User::factory()->create();
        $this->reps($below, 'acc-gym', 10, 7);    // 70% → below target (between floor and working)
        $this->assertSame('below', (new Report($below))->build()['performance'][0]['verdict']['key']);

        $floor = User::factory()->create();
        $this->reps($floor, 'acc-gym', 10, 5);    // 50% → below floor
        $this->assertSame('floor', (new Report($floor))->build()['performance'][0]['verdict']['key']);
    }

    public function test_metric_below_min_signal_reads_insufficient(): void
    {
        $this->gym('sig-gym');
        $user = User::factory()->create();
        $this->reps($user, 'sig-gym', 5, 5); // perfect but only 5 reps

        $perf = (new Report($user))->build()['performance'][0];
        $this->assertTrue($perf['insufficient']);
        $this->assertSame('insufficient', $perf['verdict']['key']);
    }

    public function test_report_is_scoped_to_one_user(): void
    {
        $this->gym('priv-gym');
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->reps($alice, 'priv-gym', 12, 12);

        $this->assertNotEmpty((new Report($alice))->build()['performance']);
        $this->assertFalse((new Report($bob))->build()['hasData']);
        $this->assertEmpty((new Report($bob))->build()['performance']);
    }

    // ---- module coverage ----------------------------------------------------

    /** A published course: one instrumented module (all gym items tagged) + one without; learner enrolled. */
    private function coveredCourse(User $learner, Gym $gym): \App\Models\Module
    {
        $course = \App\Models\Course::create(['slug' => 'cov-course', 'title' => 'Coverage Course', 'status' => 'published']);
        $module = $course->modules()->create(['title' => 'Instrumented Module', 'sort' => 0]);
        $course->modules()->create(['title' => 'Uninstrumented Module', 'sort' => 1]);
        $gym->items()->update(['module_id' => $module->id]);
        \App\Models\Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'enrolled_at' => now()]);

        return $module;
    }

    /** Real drill telemetry (coverage reads gym_attempts, not the event log): sessions × attempts at a given accuracy. */
    private function drill(User $u, Gym $gym, int $sessions, int $perSession, float $accuracy, int $latencyMs = 1000): void
    {
        $item = $gym->items->first();
        for ($s = 0; $s < $sessions; $s++) {
            $session = \App\Models\GymSession::create(['user_id' => $u->id, 'gym_id' => $gym->id, 'started_at' => now()]);
            for ($i = 0; $i < $perSession; $i++) {
                $correct = $i < (int) round($perSession * $accuracy);
                \App\Models\GymAttempt::create([
                    'gym_session_id' => $session->id, 'gym_item_id' => $item->id,
                    'selected' => $correct ? 'A' : 'B', 'is_correct' => $correct, 'latency_ms' => $latencyMs,
                ]);
            }
        }
    }

    public function test_module_coverage_reads_covered_with_sustained_pass_accuracy(): void
    {
        $gym = $this->gym('cov-gym'); // pass .80 / promote .85 / latency target 6000
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $other = User::factory()->create(['role' => UserRole::Learner]);
        $this->coveredCourse($learner, $gym);

        $this->drill($learner, $gym, sessions: 2, perSession: 6, accuracy: 1.0);
        $this->drill($other, $gym, sessions: 1, perSession: 4, accuracy: 0.0); // must not leak in

        $coverage = (new Report($learner))->build()['coverage'];
        $this->assertCount(1, $coverage);
        $this->assertSame('Coverage Course', $coverage[0]['title']);
        $this->assertSame(1, $coverage[0]['uninstrumented']);
        $this->assertCount(1, $coverage[0]['modules']);

        $m = $coverage[0]['modules'][0];
        $this->assertSame('Instrumented Module', $m['title']);
        $this->assertSame(12, $m['n']);
        $this->assertSame(2, $m['sessions']);
        $this->assertEqualsWithDelta(1.0, $m['accuracy'], 0.001);
        $this->assertTrue($m['covered']);
        $this->assertSame('target', $m['verdict']['key']);
        $this->assertSame(7, $m['rung']['level']); // accurate + fast → Reflexive
        $this->assertSame(4, $m['targetRung']['level']); // default target: Classifiable
        $this->assertTrue($m['certifiable']);
    }

    public function test_module_coverage_is_not_covered_from_a_single_session(): void
    {
        $gym = $this->gym('one-run-gym');
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->coveredCourse($learner, $gym);

        $this->drill($learner, $gym, sessions: 1, perSession: 12, accuracy: 1.0);

        $m = (new Report($learner))->build()['coverage'][0]['modules'][0];
        $this->assertSame('target', $m['verdict']['key']); // accuracy is there…
        $this->assertFalse($m['sustained']);               // …but one lucky run
        $this->assertFalse($m['covered']);
    }

    public function test_module_coverage_requires_pass_accuracy(): void
    {
        $gym = $this->gym('acc-cov-gym');
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->coveredCourse($learner, $gym);

        $this->drill($learner, $gym, sessions: 2, perSession: 8, accuracy: 0.75); // floor .60 ≤ .75 < pass .80

        $m = (new Report($learner))->build()['coverage'][0]['modules'][0];
        $this->assertSame(16, $m['n']);
        $this->assertTrue($m['sustained']);
        $this->assertSame('below', $m['verdict']['key']);
        $this->assertFalse($m['covered']);
    }

    public function test_module_coverage_below_min_signal_reads_insufficient(): void
    {
        $gym = $this->gym('thin-gym');
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->coveredCourse($learner, $gym);

        $this->drill($learner, $gym, sessions: 2, perSession: 3, accuracy: 1.0); // perfect but n=6

        $m = (new Report($learner))->build()['coverage'][0]['modules'][0];
        $this->assertTrue($m['insufficient']);
        $this->assertSame('insufficient', $m['verdict']['key']);
        $this->assertNull($m['rung']);
        $this->assertFalse($m['covered']);
    }

    public function test_courses_without_instrumented_modules_have_no_coverage_entry(): void
    {
        $gym = $this->gym('untagged-gym'); // items never tagged
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $course = \App\Models\Course::create(['slug' => 'plain-course', 'title' => 'Plain', 'status' => 'published']);
        $course->modules()->create(['title' => 'M', 'sort' => 0]);
        \App\Models\Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'enrolled_at' => now()]);

        $this->assertSame([], (new Report($learner))->build()['coverage']);
    }

    // ---- dashboard --------------------------------------------------------

    public function test_dashboard_renders_the_meter_report(): void
    {
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $this->actingAs($learner)->get('/dashboard')
            ->assertOk()
            ->assertSee('Progress')
            ->assertSee('METER');
    }

    public function test_dashboard_renders_module_coverage_evidence(): void
    {
        $gym = $this->gym('dash-cov-gym');
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->coveredCourse($learner, $gym);
        $this->drill($learner, $gym, sessions: 2, perSession: 6, accuracy: 1.0);

        $this->actingAs($learner)->get('/dashboard')
            ->assertOk()
            ->assertSee('Module coverage')
            ->assertSee('Instrumented Module')
            ->assertSee('Reflexive')
            ->assertSee('module without drill items yet');
    }
}
