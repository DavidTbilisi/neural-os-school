<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlossaryTerm extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'owner_page_id');
    }
}
