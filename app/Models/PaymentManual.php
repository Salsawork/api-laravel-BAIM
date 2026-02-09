<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentManual extends Model
{
    protected $table = 'payment_manuals';

    protected $fillable = [
        'payment_id',
        'bank_id',
        'bank_account',
        'bank_holder_name',
        'proof_image',
    ];

    public function payment()
    {
        return $this->belongsTo(Payments::class, 'payment_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }
}
