<?php

namespace App\Models;

use App\Services\Meter\Report;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Module extends Model
{
    protected $guarded = [];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort');
    }

    /** Gym items tagged as exercising this module. */
    public function gymItems(): HasMany
    {
        return $this->hasMany(GymItem::class);
    }

    /**
     * Every attempt (across all learners/sessions) on this module's gym items —
     * the evidence stream a per-module METER verdict reads. Filter by learner
     * via the session: ->whereHas('session', fn ($q) => $q->where('user_id', …)).
     */
    public function gymAttempts(): HasManyThrough
    {
        return $this->hasManyThrough(GymAttempt::class, GymItem::class, 'module_id', 'gym_item_id');
    }

    /**
     * The module-completion gate: exposure (every required lesson checked)
     * AND, when the module has an instrument (tagged gym items), coverage
     * evidence (Report::moduleEvidence `covered`). An uninstrumented module
     * can only ask for the checkbox — there is nothing to measure against.
     */
    public function completedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $required = $this->lessons->reject->optional;
        $checked = $required->isEmpty()
            || LessonCompletion::where('user_id', $user->id)
                ->whereIn('lesson_id', $required->pluck('id'))
                ->count() === $required->count();

        if (! $checked) {
            return false;
        }
        if ($this->gymItems->isEmpty()) {
            return true;
        }

        return Report::moduleEvidence($this, $user)['covered'];
    }
}
