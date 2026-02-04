<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mentor extends Model
{
    protected $table = 'mentors';

    protected $fillable = [
        'user_id',
        'user_type_id',
        'full_name',
        'age',
        'experience_years',
        'description',
        'ktp_photo',
        'bank_name',
        'bank_account',
        'bank_holder_name',
        'is_verified',
        'is_online',
        'rating_avg',
        'total_sessions',
        'cooldown_minutes',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function user_type()
    {
        return $this->belongsTo(UserType::class);
    }

    
}
