<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymAttempt extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(GymSession::class, 'gym_session_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GymItem::class, 'gym_item_id');
    }
}
