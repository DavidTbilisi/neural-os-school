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

    public function isCorrect(?string $choice): bool
    {
        return $choice !== null && $choice === $this->correct;
    }
}
