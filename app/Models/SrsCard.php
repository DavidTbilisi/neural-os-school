<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One learner's retention schedule for one gym item (Leitner ladder state).
 * Written exclusively through App\Support\Srs::record() so the scheduling
 * rules live in one place.
 */
class SrsCard extends Model
{
    protected $guarded = [];

    protected $casts = [
        'due_at' => 'datetime',
        'last_reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GymItem::class, 'gym_item_id');
    }

    /** Cards whose scheduled review date has arrived. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('due_at', '<=', now());
    }
}
