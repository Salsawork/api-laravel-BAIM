<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawRequest extends Model
{
    protected $table = 'withdraw_requests';

    protected $fillable = [
        'wallet_id',
        'withdraw_amount',
        'bank_id',
        'bank_account',
        'status',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
