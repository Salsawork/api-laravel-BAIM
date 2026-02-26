<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payments extends Model
{
    protected $table = 'payments';
    protected $fillable = [
        'consultation_id',
        'payment_method_id',
        'xendit_invoice_id',
        'xendit_external_id',
        'service_price',
        'platform_fee',
        'app_service_fee',
        'mentor_receive',
        'total',
        'status',
        'paid_at',

        'refund_amount',
        'refund_status',
        'refunded_at'
    ];

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function manual()
    {
        return $this->hasOne(PaymentManual::class,'payment_id');
    }
    

}
