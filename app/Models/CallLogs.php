<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallLogs extends Model
{
    protected $table = 'call_logs';
    protected $fillable = [
        'consultation_id', 
        'call_type', 
        'started_at', 
        'ended_at', 
        'duration_seconds', 
        'status', 
        'provider'
    ];

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }
}
