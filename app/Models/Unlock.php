<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unlock extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['components' => 'array'];
}
