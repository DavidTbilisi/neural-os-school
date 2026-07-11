<?php

namespace App\Livewire;

use App\Models\Gym;
use App\Models\GymAttempt;
use App\Models\GymItem;
use App\Models\GymSession;
use App\Support\Meter;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The gym engine: runs one recognition session as a sequence of timed rounds.
 * The Alpine timer/latency in the view calls answer()/timeout(); every attempt
 * and the session summary are logged server-side (the METER telemetry seed).
 */
#[Layout('layouts.public')]
class PlayGym extends Component
{
    public Gym $gym;

    /** Shuffled gym_item ids for this run. */
    public array $order = [];

    public int $index = 0;

    public ?int $sessionId = null;

    public string $phase = 'intro'; // intro | prompt | feedback | summary

    /** Result of the just-answered round, for the feedback panel. */
    public ?array $feedback = null;

    public function mount(string $slug): void
    {
        $gym = Gym::where('slug', $slug)->firstOrFail();
        abort_unless($gym->isPublished() || (auth()->user()?->isStaff() ?? false), 404);

        $this->gym = $gym;
    }

    public function start(): void
    {
        // Only begin from the intro or a finished session — guards against a
        // double-click spawning abandoned empty sessions.
        if (! in_array($this->phase, ['intro', 'summary'], true)) {
            return;
        }

        $itemIds = $this->gym->items()->pluck('id')->shuffle()
            ->take($this->gym->round_count)->values()->all();

        abort_if($itemIds === [], 404, 'This gym has no items.');

        $session = GymSession::create([
            'user_id' => auth()->id(),
            'gym_id' => $this->gym->id,
            'started_at' => Carbon::now(),
        ]);

        $this->sessionId = $session->id;
        $this->order = $itemIds;
        $this->index = 0;
        $this->feedback = null;
        $this->phase = 'prompt';
    }

    /** Record an answer (null = timed out) and show feedback. */
    public function answer(?string $choice = null, int $latencyMs = 0): void
    {
        if ($this->phase !== 'prompt' || ! $this->sessionId) {
            return;
        }

        $item = $this->currentItem();
        if (! $item) {
            return;
        }

        $isCorrect = $item->isCorrect($choice);

        $attempt = GymAttempt::create([
            'gym_session_id' => $this->sessionId,
            'gym_item_id' => $item->id,
            'selected' => $choice,
            'is_correct' => $isCorrect,
            'latency_ms' => max(0, $latencyMs),
        ]);

        Meter::gymRep($attempt); // emit the METER rep event

        GymSession::whereKey($this->sessionId)->update([
            'total' => $this->session()->total + 1,
            'correct' => $this->session()->correct + ($isCorrect ? 1 : 0),
        ]);

        $this->feedback = [
            'correct' => $isCorrect,
            'selected' => $choice,
            'answer' => $item->correct,
            'explanation' => $item->explanation,
            'detail' => $item->detail,
        ];
        $this->phase = 'feedback';
    }

    public function next(): void
    {
        if ($this->phase !== 'feedback') {
            return;
        }

        $this->index++;
        $this->feedback = null;

        if ($this->index >= count($this->order)) {
            $this->finalize();
            $this->phase = 'summary';

            return;
        }

        $this->phase = 'prompt';
    }

    /** Compute + persist the session summary (accuracy, median latency, stage). */
    private function finalize(): void
    {
        $session = $this->session();
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
            'stage_code' => 'L'.$this->gym->knowledgeLevelFor($accuracy, $median),
        ]);

        Meter::gymSession($session); // emit the METER session-summary event
    }

    public function currentItem(): ?GymItem
    {
        return isset($this->order[$this->index])
            ? GymItem::find($this->order[$this->index])
            : null;
    }

    private function session(): ?GymSession
    {
        return $this->sessionId ? GymSession::find($this->sessionId) : null;
    }

    public function render()
    {
        $summary = null;
        if ($this->phase === 'summary' && $this->sessionId) {
            $session = $this->session();
            $level = (int) ltrim((string) $session->stage_code, 'L');
            $rung = \App\Support\KnowledgeLadder::rung($level);

            $summary = [
                'session' => $session,
                'level' => $level,
                'rung' => $rung,
                'nextRung' => \App\Support\KnowledgeLadder::next($level),
                'ladder' => \App\Support\KnowledgeLadder::all(),
                'confusion' => $this->topConfusion($session),
                'passed' => $session->accuracy >= $this->gym->pass_accuracy,
            ];
        }

        return view('livewire.play-gym', [
            'item' => in_array($this->phase, ['prompt', 'feedback'], true) ? $this->currentItem() : null,
            'round' => $this->index + 1,
            'rounds' => count($this->order),
            'summary' => $summary,
            'ladder' => \App\Support\KnowledgeLadder::all(),
            'gymCeiling' => \App\Support\KnowledgeLadder::GYM_CEILING,
        ]);
    }

    /** Most frequent wrong "you said X, it was Y" pair among incorrect attempts. */
    private function topConfusion(GymSession $session): ?array
    {
        $pair = $session->attempts()
            ->where('is_correct', false)
            ->whereNotNull('selected')
            ->with('item:id,correct')
            ->get()
            ->groupBy(fn (GymAttempt $a) => $a->selected.' → '.($a->item->correct ?? '?'))
            ->map->count()
            ->sortDesc();

        if ($pair->isEmpty()) {
            return null;
        }

        return ['pair' => $pair->keys()->first(), 'count' => $pair->first()];
    }
}
