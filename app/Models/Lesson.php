<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $guarded = [];

    protected $casts = [
        'optional' => 'boolean',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** The wiki page this lesson points at (may be null / private). */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    public function completedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->completions()->where('user_id', $user->id)->exists();
    }
}
