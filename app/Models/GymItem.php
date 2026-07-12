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

    public function isCorrect(?string $choice): bool
    {
        return $choice !== null && $choice === $this->correct;
    }
}
