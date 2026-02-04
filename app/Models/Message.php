<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'messages';

    protected $fillable = [
        'consultation_id',
        'sender_user_id',
        'receiver_user_id',
        'message_type',
        'message',
        'attachment',
        'is_read',
    ];

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_user_id')
                    ->setConnection('mysql_user'); 
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_user_id')
                    ->setConnection('mysql_user');
    }

}
