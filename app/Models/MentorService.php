<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentorService extends Model
{
    protected $table = 'mentor_services';

    protected $fillable = [
        'mentor_id',
        'service_type_id',
        'price',
        'duration_minutes',
    ];
    public function mentor()
    {
        return $this->belongsTo(Mentor::class);
    }


    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id');
    }
    
}
