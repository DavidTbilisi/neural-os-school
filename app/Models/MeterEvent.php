<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row in the unified METER measurement log. Append-only in practice.
 */
class MeterEvent extends Model
{
    // Layers
    public const LAYER_PERFORMANCE = 'performance';
    public const LAYER_RETRIEVAL = 'retrieval';

    // Operations
    public const OP_GYM_REP = 'gym-rep';
    public const OP_GYM_SESSION = 'gym-session';
    public const OP_LESSON_COMPLETE = 'lesson-complete';
    public const OP_SRS_REVIEW = 'srs-review';

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
        'correct' => 'boolean',
        'value' => 'float',
        'context' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeLayer(Builder $q, string $layer): Builder
    {
        return $q->where('layer', $layer);
    }

    public function scopeOperation(Builder $q, string $operation): Builder
    {
        return $q->where('operation', $operation);
    }

    /** Events on/after `$days` ago (null = all time). */
    public function scopeWithinDays(Builder $q, ?int $days): Builder
    {
        return $days === null ? $q : $q->where('occurred_at', '>=', now()->subDays($days));
    }
}
