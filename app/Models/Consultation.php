<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Consultation extends Model

{
    protected $table = 'consultations';

    protected $fillable = [
        'order_number',
        'customer_user_id',
        'mentor_id',
        'service_type_id',
        'topic_category_id',
        'departure_date',
        'schedule_id',
        'price',
        'duration_minutes',
        'status',
        'payment_status',
        'started_at',
        'ended_at',
        'agora_channel',
        'queue_number',
        'expired_at'
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }
    

    public function payment()
    {
        return $this->hasOne(Payments::class,'consultation_id');
    }

    public function service()
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id');
    }

    public function topic()
    {
        return $this->belongsTo(TopicCategory::class, 'topic_category_id');
    }

    public function mentor()
{
    return $this->belongsTo(Mentor::class,'mentor_id');
}

}
