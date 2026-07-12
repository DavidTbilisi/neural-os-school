<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\PlayGym;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Gym;
use App\Models\GymAttempt;
use App\Models\GymSession;
use App\Models\MeterEvent;
use App\Models\SrsCard;
use App\Models\User;
use App\Services\Meter\Report;
use App\Support\Srs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class SrsTest extends TestCase
{
    use RefreshDatabase;

    /** A published gym with $items recognition items (A always correct). */
    private function gym(string $slug, int $items = 3): Gym
    {
        $gym = Gym::create([
            'slug' => $slug, 'title' => 'Srs Gym', 'mode' => 'recognition',
            'timer_seconds' => 8, 'round_count' => 20, 'latency_target_ms' => 6000,
            'pass_accuracy' => 0.80, 'promote_accuracy' => 0.85, 'status' => Gym::STATUS_PUBLISHED,
        ]);
        for ($i = 0; $i < $items; $i++) {
            $gym->items()->create(['prompt' => "Q{$i}", 'choices' => ['A', 'B'], 'correct' => 'A', 'sort' => $i]);
        }

        return $gym;
    }

    // ---- the ladder ---------------------------------------------------------

    public function test_first_exposure_schedules_a_card_one_day_out(): void
    {
        $gym = $this->gym('first-gym');
        $user = User::factory()->create();
        $at = Carbon::parse('2026-07-01 12:00');

        $card = Srs::record($user->id, $gym->items[0], correct: true, at: $at);

        $this->assertSame(1, $card->box);
        $this->assertSame(1, $card->streak);
        $this->assertSame(0, $card->lapses);
        $this->assertTrue($card->due_at->equalTo($at->copy()->addDay()));

        // A first-exposure miss enters box 1 too — not a lapse, nothing to fall from.
        $miss = Srs::record($user->id, $gym->items[1], correct: false, at: $at);
        $this->assertSame(1, $miss->box);
        $this->assertSame(0, $miss->streak);
        $this->assertSame(0, $miss->lapses);
    }

    public function test_correct_reviews_climb_the_ladder_and_cap(): void
    {
        $gym = $this->gym('climb-gym');
        $user = User::factory()->create();
        $item = $gym->items[0];
        $at = Carbon::parse('2026-01-01 12:00');

        foreach ([1, 2, 3, 4, 5, 6, 6] as $round => $expectedBox) {
            $card = Srs::record($user->id, $item, correct: true, at: $at->copy()->addDays($round * 100));
            $this->assertSame($expectedBox, $card->box);
        }

        $this->assertSame(60, Srs::INTERVALS[$card->box]); // capped at the 60-day box
        $this->assertSame(7, $card->streak);
        $this->assertSame(1, SrsCard::count()); // one card per (user, item), rescheduled in place
    }

    public function test_a_miss_drops_to_box_one_and_counts_a_lapse(): void
    {
        $gym = $this->gym('lapse-gym');
        $user = User::factory()->create();
        $item = $gym->items[0];
        $at = Carbon::parse('2026-01-01 12:00');

        Srs::record($user->id, $item, correct: true, at: $at);
        Srs::record($user->id, $item, correct: true, at: $at->copy()->addDays(2));   // box 3
        $card = Srs::record($user->id, $item, correct: false, at: $at->copy()->addDays(5));

        $this->assertSame(1, $card->box);
        $this->assertSame(0, $card->streak);
        $this->assertSame(1, $card->lapses);
        $this->assertTrue($card->due_at->equalTo($at->copy()->addDays(5)->addDay()));
    }

    // ---- gym integration ----------------------------------------------------

    public function test_drilling_a_gym_schedules_cards(): void
    {
        $gym = $this->gym('drill-gym');
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        Livewire::actingAs($learner)->test(PlayGym::class, ['slug' => 'drill-gym'])
            ->call('start')
            ->call('answer', 'A', 1200);

        $card = SrsCard::where('user_id', $learner->id)->sole();
        $this->assertSame(1, $card->box);
        $this->assertTrue($card->due_at->isFuture());
    }

    public function test_review_mode_queues_only_due_items_oldest_first(): void
    {
        $gym = $this->gym('queue-gym');
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        [$overdue, $fresh] = [$gym->items[0], $gym->items[1]]; // items[2] never seen

        Srs::record($learner->id, $overdue, correct: true, at: now()->subDays(3)); // due 2 days ago
        Srs::record($learner->id, $fresh, correct: true, at: now());               // due tomorrow

        $component = Livewire::actingAs($learner)
            ->withQueryParams(['mode' => 'review'])
            ->test(PlayGym::class, ['slug' => 'queue-gym'])
            ->call('start');

        $this->assertSame([$overdue->id], $component->get('order'));

        // Answering the review reschedules the card out of the due set…
        $component->call('answer', 'A', 900);
        $this->assertSame(2, $card = SrsCard::where('gym_item_id', $overdue->id)->sole()->box);
        $this->assertSame(0, SrsCard::where('user_id', $learner->id)->due()->count());

        // …and lands a retrieval-layer event keyed to the attempt.
        $attempt = GymAttempt::latest('id')->first();
        $event = MeterEvent::where('source_key', 'srs_review:'.$attempt->id)->sole();
        $this->assertSame(MeterEvent::LAYER_RETRIEVAL, $event->layer);
        $this->assertSame(MeterEvent::OP_SRS_REVIEW, $event->operation);
    }

    public function test_review_with_nothing_due_does_not_start(): void
    {
        $this->gym('empty-review-gym');
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        Livewire::actingAs($learner)
            ->withQueryParams(['mode' => 'review'])
            ->test(PlayGym::class, ['slug' => 'empty-review-gym'])
            ->assertSee('Nothing due')
            ->call('start')
            ->assertSet('phase', 'intro');

        $this->assertSame(0, GymSession::count());
    }

    public function test_drill_intro_nudges_toward_due_reviews(): void
    {
        $gym = $this->gym('nudge-gym');
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        Srs::record($learner->id, $gym->items[0], correct: true, at: now()->subDays(2));

        Livewire::actingAs($learner)->test(PlayGym::class, ['slug' => 'nudge-gym'])
            ->assertSee('1 card due for review');
    }

    // ---- METER surfacing ------------------------------------------------------

    public function test_report_carries_retention_per_module_and_due_glance(): void
    {
        $gym = $this->gym('report-gym');
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $course = Course::create(['slug' => 'ret-course', 'title' => 'Retention Course', 'status' => 'published']);
        $module = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $gym->items()->update(['module_id' => $module->id]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'enrolled_at' => now()]);

        Srs::record($learner->id, $gym->items[0], correct: true, at: now()->subDays(3)); // due
        Srs::record($learner->id, $gym->items[1], correct: true, at: now());             // current

        $report = (new Report($learner))->build();
        $retention = $report['coverage'][0]['modules'][0]['retention'];

        $this->assertSame(['scheduled' => 2, 'due' => 1, 'rate' => 0.5], $retention);
        $this->assertSame(1, $report['glance']['reviewsDue']);
        $this->assertSame('report-gym', $report['coverage'][0]['reviewGym']);
    }

    public function test_dashboard_shows_due_reviews_and_retention(): void
    {
        $gym = $this->gym('dash-srs-gym');
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $course = Course::create(['slug' => 'dash-ret', 'title' => 'Dash Ret', 'status' => 'published']);
        $module = $course->modules()->create(['title' => 'M1', 'sort' => 0]);
        $gym->items()->update(['module_id' => $module->id]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'enrolled_at' => now()]);
        Srs::record($learner->id, $gym->items[0], correct: true, at: now()->subDays(2));

        $this->actingAs($learner)->get('/dashboard')
            ->assertOk()
            ->assertSee('reviews due')
            ->assertSee('review 1 due')
            ->assertSee('mode=review');
    }

    // ---- backfill -------------------------------------------------------------

    public function test_backfill_rebuilds_schedules_from_attempt_history(): void
    {
        $gym = $this->gym('bf-srs-gym');
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $item = $gym->items[0];

        $session = GymSession::create(['user_id' => $learner->id, 'gym_id' => $gym->id, 'started_at' => now()]);
        foreach ([now()->subDays(3), now()->subDay()] as $when) {
            GymAttempt::forceCreate([
                'gym_session_id' => $session->id, 'gym_item_id' => $item->id,
                'selected' => 'A', 'is_correct' => true, 'latency_ms' => 1000,
                'created_at' => $when, 'updated_at' => $when,
            ]);
        }

        Artisan::call('srs:backfill');
        $card = SrsCard::sole();
        $this->assertSame(2, $card->box);
        $this->assertSame(2, $card->streak);
        $this->assertTrue($card->due_at->equalTo(now()->subDay()->addDays(3)->startOfSecond()));

        Artisan::call('srs:backfill'); // rebuild converges — no duplicates, same state
        $this->assertSame(1, SrsCard::count());
        $this->assertSame(2, SrsCard::sole()->box);
    }
}
