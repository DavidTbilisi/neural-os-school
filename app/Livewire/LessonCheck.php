<?php

namespace App\Livewire;

use App\Models\Gym;
use App\Models\GymItem;
use App\Models\GymSession;
use App\Models\Lesson;
use App\Support\GymScoring;
use Livewire\Component;

/**
 * A small, self-paced "check your understanding" embedded at the end of a
 * lesson — the items tagged to this specific lesson (GymItem.lesson_id),
 * answered through the exact same scorer PlayGym uses (GymScoring), so the
 * reps count toward the lesson's parent module's coverage evidence exactly
 * like a full gym drill would. Deliberately untimed and chrome-light (no
 * round-count picker, no Knowledge Ladder summary screen) — unlike PlayGym,
 * this is always a nested component embedded in a lesson page, never a
 * standalone route.
 */
class LessonCheck extends Component
{
    public Lesson $lesson;

    /** This lesson's tagged gym_item ids, in authoring order. */
    public array $order = [];

    public int $index = 0;

    public ?int $sessionId = null;

    /** The Gym these items belong to — resolved once in start() so finalize() never re-derives it from a now-exhausted item index. */
    public ?int $gymId = null;

    public string $phase = 'intro'; // intro | prompt | feedback | done

    /** Result of the just-answered round, for the feedback panel. */
    public ?array $feedback = null;

    // Mount param is deliberately NOT named `lesson` — Livewire's nested-
    // component initialization auto-assigns any param whose name matches a
    // public property name directly onto that property before mount() runs
    // (bypassing this method's body entirely for that value). Learned the
    // hard way on ShowLesson: same-named string route params crashed trying
    // to assign onto a typed model property. Here it wouldn't crash (both
    // sides are a Lesson), but it would skip loading $this->order below.
    public function mount(Lesson $forLesson): void
    {
        $this->lesson = $forLesson;
        $this->order = GymItem::where('lesson_id', $forLesson->id)->orderBy('sort')->pluck('id')->all();
    }

    public function start(): void
    {
        if ($this->order === [] || ! in_array($this->phase, ['intro', 'done'], true)) {
            return;
        }

        if (! auth()->check()) {
            $this->redirect(route('login'));

            return;
        }

        $gym = GymItem::find($this->order[0])?->gym;
        abort_if(! $gym, 404);

        $session = GymSession::create([
            'user_id' => auth()->id(),
            'gym_id' => $gym->id,
            'started_at' => now(),
        ]);

        $this->sessionId = $session->id;
        $this->gymId = $gym->id;
        $this->index = 0;
        $this->feedback = null;
        $this->phase = 'prompt';
    }

    /** Record an answer and show feedback — untimed, so latencyMs defaults to 0. */
    public function answer(?string $choice = null, int $latencyMs = 0): void
    {
        if ($this->phase !== 'prompt' || ! $this->sessionId) {
            return;
        }

        $item = $this->currentItem();
        if (! $item) {
            return;
        }

        $attempt = GymScoring::record($this->session(), $item, $choice, $latencyMs);

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
            GymScoring::finalizeSession($this->session(), Gym::find($this->gymId));
            $this->phase = 'done';

            return;
        }

        $this->phase = 'prompt';
    }

    public function currentItem(): ?GymItem
    {
        return isset($this->order[$this->index]) ? GymItem::find($this->order[$this->index]) : null;
    }

    private function session(): ?GymSession
    {
        return $this->sessionId ? GymSession::find($this->sessionId) : null;
    }

    public function render()
    {
        return view('livewire.lesson-check', [
            'item' => in_array($this->phase, ['prompt', 'feedback'], true) ? $this->currentItem() : null,
            'round' => $this->index + 1,
            'rounds' => count($this->order),
            'session' => $this->phase === 'done' ? $this->session() : null,
        ]);
    }
}
