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
        'bank_id',
        'bank_account',
        'bank_holder_name',
        'is_verified',
        'is_online',
        'rating_avg',
        'total_sessions',
        'cooldown_minutes',
        'current_consultation_id',
        'last_seen'
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user_type()
    {
        return $this->belongsTo(UserType::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function services()
    {
        return $this->hasMany(MentorService::class,'mentor_id');
    }
    
    public function topics()
    {
        return $this->hasMany(MentorTopic::class,'mentor_id');
    }

    // current_consultation_id
    

}
