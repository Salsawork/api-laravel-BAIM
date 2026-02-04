<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    protected $table = 'user_types';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];
}
