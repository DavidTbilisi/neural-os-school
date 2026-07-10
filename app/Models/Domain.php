<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    public $timestamps = false;
    public $incrementing = false; // id is the permanent 1..10 number
    protected $keyType = 'int';
    protected $fillable = ['id', 'name'];

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }
}
