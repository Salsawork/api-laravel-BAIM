<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payments extends Model
{
    protected $table = 'payments';
    protected $fillable = ['
        consultation_id',
        'payment_method',
        'xendit_invoice_id',
        'xendit_external_id',
        'service_price',
        'platform_fee',
        'total',
        'status',
        'paid_at'
    ];
}
