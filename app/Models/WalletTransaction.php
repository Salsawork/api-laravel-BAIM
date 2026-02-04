<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $table = 'wallet_transactions';

    protected $fillable = [
        'wallet_id',
        'consultation_id',
        'transaction_amount',
        'wallet_type',
    ];

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
