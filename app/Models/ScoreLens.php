<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreLens extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'extra' => 'array',
        'complexity' => 'float',
        'acquirement' => 'float',
        'absorbed' => 'float',
    ];

    public static function total(): ?self
    {
        return static::where('lens_type', 'total')->first();
    }
}
