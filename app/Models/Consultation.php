<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    protected $table = 'consultations';

    protected $fillable = [
        'order_number',
        'customer_user_id',
        'mentor_id',
        'service_type_id',
        'topic_category_id',
        'schedule_id',
        'price',
        'duration_minutes',
        'status',
        'payment_status',
        'started_at',
        'ended_at',
    ];

    public function customer()
{
    return $this->belongsTo(User::class, 'customer_user_id')
                ->setConnection('mysql_user');
}

}
