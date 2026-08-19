<?php

namespace App\Livewire;

use App\Models\Gym;
use App\Models\GymAttempt;
use App\Models\GymItem;
use App\Models\GymSession;
use App\Models\SrsCard;
use App\Support\GymScoring;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The gym engine: runs one recognition session as a sequence of timed rounds.
 * The Alpine timer/latency in the view calls answer()/timeout(); every attempt
 * and the session summary are logged server-side (the METER telemetry seed).
 *
 * Two modes share the engine: 'drill' (random items — first-pass performance)
 * and 'review' (?mode=review — only SRS-due items, oldest due first; retention
 * practice). Every attempt in either mode reschedules the item's SRS card.
 */
#[Layout('layouts.public')]
class PlayGym extends Component
{
    public Gym $gym;

    #[Url]
    public string $mode = 'drill'; // drill | review

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

        $itemIds = $this->isReview()
            ? $this->dueCardQuery()->orderBy('due_at')
                ->limit($this->gym->round_count)->pluck('gym_item_id')->all()
            : $this->gym->items()->pluck('id')->shuffle()
                ->take($this->gym->round_count)->values()->all();

        if ($this->isReview() && $itemIds === []) {
            return; // nothing due — the intro says so instead of starting an empty session
        }

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

        $attempt = GymScoring::record($this->session(), $item, $choice, $latencyMs, $this->isReview());

        $this->feedback = [
            'correct' => $attempt->is_correct,
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
        GymScoring::finalizeSession($this->session(), $this->gym);
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

    public function isReview(): bool
    {
        return $this->mode === 'review';
    }

    /** The learner's due cards on this gym's items. */
    private function dueCardQuery()
    {
        return SrsCard::where('user_id', auth()->id())->due()
            ->whereIn('gym_item_id', $this->gym->items()->pluck('id'));
    }

    public function render()
    {
        $summary = null;
        if ($this->phase === 'summary' && $this->sessionId) {
            $session = $this->session();
            $level = (int) ltrim((string) $session->stage_code, 'L');
            $rung = \App\Support\KnowledgeLadder::rung($level);

            // A floored run reports both numbers: the rung it was awarded and
            // the rung its raw accuracy+speed would have read, so the learner
            // sees what the blind spot cost rather than an unexplained plateau.
            $blindSpots = $session->blind_spots ?? [];
            $band = $blindSpots === []
                ? $level
                : $this->gym->knowledgeLevelFor((float) $session->accuracy, $session->median_latency_ms);

            $summary = [
                'session' => $session,
                'level' => $level,
                'rung' => $rung,
                'nextRung' => \App\Support\KnowledgeLadder::next($level),
                'ladder' => \App\Support\KnowledgeLadder::all(),
                'confusion' => $this->topConfusion($session),
                'passed' => $session->accuracy >= $this->gym->pass_accuracy,
                'blindSpots' => $blindSpots,
                'withheld' => $band > $level ? \App\Support\KnowledgeLadder::rung($band) : null,
            ];
        }

        return view('livewire.play-gym', [
            'item' => in_array($this->phase, ['prompt', 'feedback'], true) ? $this->currentItem() : null,
            'round' => $this->index + 1,
            'rounds' => count($this->order),
            'summary' => $summary,
            'ladder' => \App\Support\KnowledgeLadder::all(),
            'gymCeiling' => \App\Support\KnowledgeLadder::GYM_CEILING,
            'dueCount' => ($this->phase === 'intro' && auth()->check()) ? $this->dueCardQuery()->count() : 0,
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
