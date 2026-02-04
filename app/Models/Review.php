<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $table = 'reviews';
    protected $fillable = [
        'customer_user_id', 
        'consultation_id', 
        'rating', 
        'comment'
    ];

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }
}
