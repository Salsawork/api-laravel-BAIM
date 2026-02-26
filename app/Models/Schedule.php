<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $table = 'mentor_schedules';

    protected $fillable = [
        'mentor_id',
        'day_of_week',
        'date',
        'start_time',
        'end_time',
        'price',
        'is_active'
    ];

    public function mentor()
    {
        return $this->belongsTo(Mentor::class);
    }
}
