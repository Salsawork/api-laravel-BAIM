<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawRequest extends Model
{
    protected $table = 'withdraw_requests';

    protected $fillable = [
        'wallet_id',
        'withdraw_amount',
        'bank_name',
        'bank_account',
        'status',
    ];
}
