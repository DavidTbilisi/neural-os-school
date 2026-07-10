<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    public $timestamps = false;
    public $incrementing = false; // id is the 1..10 maturity number
    protected $keyType = 'int';
    protected $fillable = ['id', 'name'];
}
