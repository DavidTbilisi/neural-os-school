<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_UNLISTED = 'unlisted';
    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITIES = [
        self::VISIBILITY_PRIVATE => 'Private',
        self::VISIBILITY_UNLISTED => 'Unlisted',
        self::VISIBILITY_PUBLIC => 'Public',
    ];

    protected $guarded = [];

    protected $casts = [
        'last_updated' => 'date',
        'is_meta' => 'boolean',
        'axis_clean' => 'boolean',
        'room' => 'integer',
        'level' => 'integer',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function outboundLinks(): HasMany
    {
        return $this->hasMany(Link::class, 'source_page_id');
    }

    public function inboundLinks(): HasMany
    {
        return $this->hasMany(Link::class, 'target_page_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function isPublished(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    /** Listed on the public site. */
    public function scopePublic($query)
    {
        return $query->where('visibility', self::VISIBILITY_PUBLIC);
    }

    /** Reachable by direct link (public or unlisted), i.e. not private. */
    public function scopeViewable($query)
    {
        return $query->whereIn('visibility', [self::VISIBILITY_PUBLIC, self::VISIBILITY_UNLISTED]);
    }
}
