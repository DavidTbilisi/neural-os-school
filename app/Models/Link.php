<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Link extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['resolved' => 'boolean'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'source_page_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'target_page_id');
    }
}
