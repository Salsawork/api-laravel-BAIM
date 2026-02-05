<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $table = 'wallets';
    protected $fillable = [
        'mentor_id', 
        'balance',
    ];

    public function mentor()
    {
        return $this->belongsTo(Mentor::class);
    }
}
