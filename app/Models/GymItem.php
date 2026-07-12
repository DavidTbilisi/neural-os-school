<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'choices' => 'array',
        'tags' => 'array',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /** The course module this item exercises (nullable — untagged items are gym-wide). */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * The specific lesson this item checks (nullable — display-scoping only).
     * A lesson-tagged item's module_id is always set to the lesson's parent
     * module too, so Module::gymAttempts()/Report::moduleEvidence() need no
     * awareness of lesson_id at all — it only decides what renders on which
     * lesson page.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isCorrect(?string $choice): bool
    {
        return $choice !== null && $choice === $this->correct;
    }
}
