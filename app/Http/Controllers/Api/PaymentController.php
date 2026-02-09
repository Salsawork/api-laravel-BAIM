<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Consultation;
use App\Models\Payments;
use App\Models\PaymentManual;
use App\Models\Fee;

use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;

class PaymentController extends Controller
{

    // Create Payment transfer manual
    public function paymentManual(Request $request, $consultationId)
    {
        $request->validate([
            'proof_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'bank_id' => 'required|exists:mst_bank,id_bank',
            'bank_account' => 'required|string|max:50',
            'bank_holder_name' => 'required|string|max:100'
        ]);
    
        $consult = Consultation::findOrFail($consultationId);
    
        if ($consult->payment_status == 'paid') {
            return response()->json([
                'message'=>'Already paid'
            ],400);
        }
    
        $payment = Payments::where('consultation_id',$consult->id)->first();
    
        $fee = Fee::where('key_name','app_fee')->value('value') ?? 0;

        if (!$payment) {
            $payment = Payments::create([
                'consultation_id' => $consult->id,
                'payment_method_id' => 1,
                'service_price' => $consult->price,
                'platform_fee' => $fee,
                'total' => $consult->price,
                'status' => 'pending'
            ]);
        }
    
        $file = $request->file('proof_image');
        $filename = 'manual_'.$consult->id.'_'.time().'.'.$file->getClientOriginalExtension();
        $file->move(public_path('uploads/manual_payments'), $filename);
    
        $path = 'uploads/manual_payments/'.$filename;
    
        // UPDATE PAYMENT
        $payment->update([
            'payment_method_id'=>1,
            'status'=>'pending'
        ]);
    
        PaymentManual::updateOrCreate(
            ['payment_id'=>$payment->id],
            [
                'bank_id'=>$request->bank_id,
                'bank_account'=>$request->bank_account,
                'bank_holder_name'=>$request->bank_holder_name,
                'proof_image'=>$filename
            ]
        );
    
        return response()->json([
            'success'=>true,
            'message'=>'Bukti transfer uploaded',
            'data'=>$payment
        ]);
    }
    

    public function verifyManual($paymentId)
    {
        $payment = Payments::findOrFail($paymentId);

        if ($payment->status == 'paid') {
            return response()->json(['message'=>'Already verified']);
        }

        DB::transaction(function () use ($payment){

            $payment->update([
                'status'=>'paid',
                'paid_at'=>now()
            ]);

            Consultation::where('id',$payment->consultation_id)
                ->update([
                    'payment_status'=>'paid',
                    'status'=>'active'
                ]);
        });

        return response()->json([
            'success'=>true,
            'message'=>'Manual payment verified'
        ]);
    }

    // Create Payment xendit User
    public function paymentXendit($consultationId)
    {
        Configuration::setXenditKey(config('services.xendit.secret_key'));
        $consultation = Consultation::with('customer')->findOrFail($consultationId);


        if ($consultation->payment_status == 'paid') {
            return response()->json([
                'message' => 'Sudah dibayar'
            ], 400);
        }

        $user = $consultation->customer;

        $apiInstance = new InvoiceApi();

        $createInvoice = new CreateInvoiceRequest([
            'external_id' => $consultation->order_number,
            'amount' => (int) $consultation->price,
            'description' => 'BAIM Consultation',
            'invoice_duration' => 3600,
            'currency' => 'IDR',
            'customer' => [
                'given_names' => $user->name ?? 'User',
                'email' => $user->email ?? 'user@mail.com'
            ]
        ]);

        $result = $apiInstance->createInvoice($createInvoice);
        $fee = Fee::where('key_name','app_fee')->value('value') ?? 0;

        Payments::create([
            'consultation_id' => $consultation->id,
            'xendit_invoice_id' => $result['id'],
            'xendit_external_id' => $consultation->order_number,
            'service_price' => $consultation->price,
            'platform_fee' => $fee,
            'total' => $consultation->price ,
            'status' => 'pending'
        ]);

        return response()->json([
            'invoice_url' => $result['invoice_url']
        ]);
    }

    // WEBHOOK XENDIT
    public function handle(Request $request)
    {

        $callbackToken = config('services.xendit.callback_token');
    
        if ($request->header('x-callback-token') !== $callbackToken) {
            return response()->json(['message'=>'invalid token'],403);
        }
    
        $data = $request->all();
    
        $externalId = $data['external_id'] ?? null;
        $status = $data['status'] ?? null;
    
        if (!$externalId) return response()->json(['ok'=>true]);
    
        $payment = Payments::where('xendit_external_id',$externalId)->first();
        if (!$payment) return response()->json(['ok'=>true]);
    
        if ($payment->status == 'paid') {
            return response()->json(['ok'=>true]);
        }
    
        // ambil method xendit
        $method = \App\Models\PaymentMethod::where('code','xendit')->first();
    
        if ($status == 'PAID') {
    
            DB::transaction(function () use ($payment,$method){
    
                $payment->update([
                    'status'=>'paid',
                    'paid_at'=>now(),
                    'payment_method_id'=>$method->id ?? null
                ]);
    
                Consultation::where('id',$payment->consultation_id)
                    ->update([
                        'payment_status'=>'paid',
                        'status'=>'waiting'
                    ]);
            });
        }
    
        if ($status == 'EXPIRED') {
    
            $payment->update(['status'=>'expired']);
    
            Consultation::where('id',$payment->consultation_id)
                ->update([
                    'payment_status'=>'expired',
                    'status'=>'cancelled'
                ]);
        }
    
        return response()->json(['success'=>true]);
    }
    
    // CEK STATUS Payment
    public function checkStatus($orderNumber)
    {
        $consultation = Consultation::where('order_number', $orderNumber)->first();

        return response()->json([
            'status' => $consultation->status,
            'payment_status' => $consultation->payment_status
        ]);
    }

    // Masuk room User
    public function joinRoom($orderNumber)
    {
        $consultation = Consultation::where('order_number', $orderNumber)->first();

        if ($consultation->payment_status != 'paid') {
            return response()->json(['message' => 'Belum bayar'], 403);
        }

        $consultation->update([
            'status' => 'ongoing',
            'started_at' => now()
        ]);

        return response()->json([
            'success' => true
        ]);
    }

    
}
