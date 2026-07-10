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

    // ---- dashboard --------------------------------------------------------

    public function test_dashboard_renders_the_meter_report(): void
    {
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $this->actingAs($learner)->get('/dashboard')
            ->assertOk()
            ->assertSee('Progress')
            ->assertSee('METER');
    }
}
