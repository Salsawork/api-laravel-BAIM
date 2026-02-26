<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fee extends Model
{
    protected $table = 'fees';

    protected $fillable = [
        'key_name',
        'value',
        'description',
    ];

    public static function getValue($key, $default = 0)
    {
        return static::where('key_name',$key)->value('value') ?? $default;
    }
}
