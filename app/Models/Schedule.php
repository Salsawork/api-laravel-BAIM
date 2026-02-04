<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $table = 'schedules';

   protected $fillable = [
        'mentor_id',
        'date',
        'start_time',
        'end_time',
        'is_booked',
    ];

    public function mentor()
    {
        return $this->belongsTo(Mentor::class);
    }
}
