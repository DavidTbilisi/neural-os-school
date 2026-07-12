<?php

namespace App\Models;

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
}
