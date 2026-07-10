<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A spec-driven recognition drill. Items are timed classification prompts;
 * play-throughs (sessions + attempts) carry the per-learner telemetry.
 */
class Gym extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PUBLISHED => 'Published',
    ];

    protected $guarded = [];

    protected $casts = [
        'stages' => 'array',
        'pass_accuracy' => 'float',
        'promote_accuracy' => 'float',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(GymItem::class)->orderBy('sort');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GymSession::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * The stage read for a completed session: the highest stage whose accuracy
     * and latency thresholds are met. Stages are ordered strongest-first in the
     * spec; returns the stage `code`, or null if none/no stages defined.
     */
    public function stageFor(float $accuracy, ?int $medianLatencyMs): ?string
    {
        foreach (($this->stages ?? []) as $stage) {
            $minAcc = (float) ($stage['min_accuracy'] ?? 0);
            $maxLat = $stage['max_latency_ms'] ?? null;

            $accOk = $accuracy >= $minAcc;
            $latOk = $maxLat === null || ($medianLatencyMs !== null && $medianLatencyMs <= $maxLat);

            if ($accOk && $latOk) {
                return $stage['code'] ?? null;
            }
        }

        return null;
    }

    /** Median of a list of latency values (ms), or null when empty. */
    public static function median(Collection $values): ?int
    {
        $sorted = $values->filter(fn ($v) => $v !== null)->sort()->values();
        if ($sorted->isEmpty()) {
            return null;
        }

        $mid = intdiv($sorted->count(), 2);

        return $sorted->count() % 2
            ? (int) $sorted[$mid]
            : (int) round(($sorted[$mid - 1] + $sorted[$mid]) / 2);
    }
}
