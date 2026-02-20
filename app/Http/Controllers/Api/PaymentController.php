<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Consultation;
use App\Models\Payments;
use App\Models\PaymentMethod;
use App\Models\PaymentManual;
use App\Models\Fee;
use App\Models\Mentor;

use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;

class PaymentController extends Controller
{

    // private function mentorHasActiveSlot($mentorId)
    // {
    //     return Consultation::where('mentor_id',$mentorId)
    //         ->where('payment_status','paid')
    //         ->whereNotIn('status',['completed','cancelled'])
    //         ->where(function($q){
    //             $q->whereNull('started_at')
    //             ->orWhereRaw("
    //                 DATE_ADD(started_at, INTERVAL duration_minutes + 10 MINUTE) > NOW()
    //             ");
    //         })
    //         ->lockForUpdate()
    //         ->exists();
    // }

    // Create Payment transfer manual
    public function paymentManual(Request $request, $consultationId)
    {
        $request->validate([
            'proof_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'bank_id' => 'required|exists:mst_bank,id_bank',
            'bank_account' => 'required|string|max:50',
            'bank_holder_name' => 'required|string|max:100'
        ]);
    
        $consult = Consultation::find($consultationId);
    
        if(!$consult){
            return response()->json([
                'success'=>false,
                'message'=>'Consultation tidak ditemukan'
            ],404);
        }
    
        $userId = auth()->id();
    
        // bukan user pemilik
        if($consult->customer_user_id != $userId){
            return response()->json([
                'success'=>false,
                'message'=>'Hanya user pemesan yang boleh bayar'
            ],403);
        }
    
        // mentor tidak boleh bayar
        if(Mentor::where('user_id',$userId)->exists()){
            return response()->json([
                'success'=>false,
                'message'=>'Akun mentor tidak bisa melakukan pembayaran'
            ],403);
        }
    
        // sudah dibayar
        if($consult->payment_status == 'paid'){
            return response()->json([
                'success'=>false,
                'message'=>'Consultation sudah dibayar'
            ],400);
        }
    
        $amount = $consult->mentor->user_type_id == 1
        ? $consult->total_price   // muthowif
        : $consult->price;        // konsultan

        $fee = Fee::where('key_name','app_fee')->value('value') ?? 0;

        $payment = Payments::firstOrCreate(
            ['consultation_id'=>$consult->id],
            [
                'payment_method_id'=>1,
                'service_price'=>$amount,
                'platform_fee'=>$fee,
                'total'=>$amount,
                'status'=>'pending'
            ]
        );

    
        // upload file
        $file = $request->file('proof_image');
        $filename = 'manual_'.$consult->id.'_'.time().'.'.$file->getClientOriginalExtension();
        $file->move(public_path('uploads/manual_payments'), $filename);
    
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
            'message'=>'Bukti transfer berhasil dikirim, menunggu verifikasi admin'
        ]);
    }    

    public function verifyManual($paymentId)
    {
        DB::beginTransaction();

        try{

            $payment = Payments::lockForUpdate()->find($paymentId);
            $consult = Consultation::lockForUpdate()->find($payment->consultation_id);
            $mentor  = Mentor::lockForUpdate()->find($consult->mentor_id);

            if($consult->payment_status == 'paid'){
                DB::commit();
                return response()->json(['success'=>true,'message'=>'Already verified']);
            }

            // slot sudah diambil?
            if(
                $mentor->current_consultation_id &&
                $mentor->current_consultation_id != $consult->id
            ){
                $payment->update(['status'=>'failed']);
                DB::commit();
                return response()->json(['success'=>false,'message'=>'Slot taken']);
            }

            // PAYMENT SUCCESS
            $payment->update([
                'status'=>'paid',
                'paid_at'=>now()
            ]);

            $mentor->update([
                'current_consultation_id'=>$consult->id
            ]);

            // start session + buffer 10 menit
            $consult->update([
                'payment_status'=>'paid',
                'status'=>'active',
                'started_at'=>now(),
                'ended_at'=>now()->addMinutes($consult->duration_minutes + 10)
            ]);

            DB::commit();

            return response()->json([
                'success'=>true,
                'message'=>'Payment verified & session started'
            ]);

        }catch(\Exception $e){
            DB::rollBack();
            return response()->json(['error'=>$e->getMessage()],500);
        }
    }

    // public function verifyManual($paymentId)
    // {
    //     DB::beginTransaction();
    
    //     try{
    
    //         $payment = Payments::lockForUpdate()->find($paymentId);
    
    //         if(!$payment){
    //             DB::rollBack();
    //             return response()->json([
    //                 'success'=>false,
    //                 'message'=>'Payment tidak ditemukan'
    //             ],404);
    //         }
    
    //         // hanya manual
    //         if($payment->payment_method_id != 1){
    //             DB::rollBack();
    //             return response()->json([
    //                 'success'=>false,
    //                 'message'=>'Payment ini bukan manual transfer'
    //             ],400);
    //         }
    
    //         $consult = Consultation::lockForUpdate()->find($payment->consultation_id);
    //         $mentor  = Mentor::lockForUpdate()->find($consult->mentor_id);
    
    //         if(!$consult || !$mentor){
    //             DB::rollBack();
    //             return response()->json([
    //                 'success'=>false,
    //                 'message'=>'Consultation / mentor tidak ditemukan'
    //             ],404);
    //         }
    
    //         // =========================================
    //         // 🔴 RETRY SAFETY (admin klik verify 2x)
    //         // =========================================
    //         if($consult->payment_status == 'paid'){
    //             $payment->update([
    //                 'status'=>'paid',
    //                 'paid_at'=>now()
    //             ]);
    
    //             DB::commit();
    
    //             return response()->json([
    //                 'success'=>true,
    //                 'message'=>'Sudah pernah diverifikasi sebelumnya'
    //             ]);
    //         }
    
    //         // =========================================
    //         // 🔴 SLOT MENTOR SUDAH DIAMBIL?
    //         // =========================================
    //         if(
    //             $mentor->current_consultation_id && 
    //             $mentor->current_consultation_id != $consult->id
    //         ){
    //             $payment->update(['status'=>'failed']);
    
    //             DB::commit();
    
    //             return response()->json([
    //                 'success'=>false,
    //                 'message'=>'Slot sudah diambil user lain'
    //             ],409);
    //         }
    
    //         // =========================================
    //         // ✅ PAYMENT SUCCESS
    //         // =========================================
    //         $payment->update([
    //             'status'=>'paid',
    //             'paid_at'=>now()
    //         ]);
    
    //         // LOCK SLOT KE MENTOR
    //         $mentor->update([
    //             'current_consultation_id'=>$consult->id
    //         ]);
    
    //         // START SESSION
    //         $extraTime = 10; // buffer
    //         $consult->update([
    //             'payment_status'=>'paid',
    //             'status'=>'active',
    //             'started_at'=>now(),
    //             'ended_at'=>now()->addMinutes($consult->duration_minutes + $extraTime)
    //         ]);
    
    //         DB::commit();
    
    //         return response()->json([
    //             'success'=>true,
    //             'message'=>'Payment manual verified & konsultasi dimulai'
    //         ]);
    
    //     } catch(\Exception $e){
    //         DB::rollBack();
    //         return response()->json([
    //             'success'=>false,
    //             'error'=>$e->getMessage()
    //         ],500);
    //     }
    // }
    

    // Create Payment xendit User
    public function paymentXendit($consultationId)
    {
        Configuration::setXenditKey(config('services.xendit.secret_key'));

        $consult = Consultation::with('customer','mentor')->findOrFail($consultationId);

        if($consult->payment_status == 'paid'){
            return response()->json(['message'=>'Sudah dibayar'],400);
        }

        // ===============================
        // 🔥 HITUNG TOTAL
        // ===============================
        $amount = $consult->mentor->user_type_id == 1
            ? $consult->total_price
            : $consult->price;

        $api = new InvoiceApi();

        $createInvoice = new CreateInvoiceRequest([
            'external_id' => $consult->order_number,
            'amount' => (int)$amount,
            'description' => 'BAIM Payment',
            'invoice_duration' => 3600,
            'currency' => 'IDR',
            'customer' => [
                'given_names' => $consult->customer->name ?? 'User',
                'email' => $consult->customer->email ?? 'user@mail.com'
            ]
        ]);

        $result = $api->createInvoice($createInvoice);

        $fee = Fee::where('key_name','app_fee')->value('value') ?? 0;

        Payments::create([
            'consultation_id'=>$consult->id,
            'xendit_invoice_id'=>$result['id'],
            'xendit_external_id'=>$consult->order_number,
            'payment_method_id'=>2,
            'service_price'=>$amount,
            'platform_fee'=>$fee,
            'total'=>$amount,
            'status'=>'pending'
        ]);

        return response()->json([
            'invoice_url'=>$result['invoice_url']
        ]);
    }

    // public function paymentXendit($consultationId)
    // {
    //     Configuration::setXenditKey(config('services.xendit.secret_key'));
    //     $consultation = Consultation::with('customer')->findOrFail($consultationId);

    //     if(!$consultation){
    //         return response()->json([
    //             'success'=>false,
    //             'message'=>'Consultation tidak ditemukan'
    //         ],404);
    //     }
    
    //     $userId = auth()->id();
    
    //     // bukan pemilik consultation
    //     if($consultation->customer_user_id != $userId){
    //         return response()->json([
    //             'success'=>false,
    //             'message'=>'Hanya user pemesan yang boleh melakukan pembayaran'
    //         ],403);
    //     }
    
    //     // kalau mentor login
    //     $mentor = Mentor::where('user_id',$userId)->first();
    //     if($mentor){
    //         return response()->json([
    //             'success'=>false,
    //             'message'=>'Akun mentor tidak dapat melakukan pembayaran'
    //         ],403);
    //     }

    //     // kalau consultation sudah dibayar
    //     if ($consultation->payment_status == 'paid') {
    //         return response()->json([
    //             'message' => 'Sudah dibayar'
    //         ], 400);
    //     }

    //     $user = $consultation->customer;

    //     $apiInstance = new InvoiceApi();

    //     $createInvoice = new CreateInvoiceRequest([
    //         'external_id' => $consultation->order_number,
    //         'amount' => (int) $consultation->price,
    //         'description' => 'BAIM Consultation',
    //         'invoice_duration' => 3600,
    //         'currency' => 'IDR',
    //         'customer' => [
    //             'given_names' => $user->name ?? 'User',
    //             'email' => $user->email ?? 'user@mail.com'
    //         ]
    //     ]);

    //     $result = $apiInstance->createInvoice($createInvoice);
    //     $fee = Fee::where('key_name','app_fee')->value('value') ?? 0;

    //     Payments::create([
    //         'consultation_id' => $consultation->id,
    //         'xendit_invoice_id' => $result['id'],
    //         'xendit_external_id' => $consultation->order_number,
    //         'payment_method_id' => 2,
    //         'service_price' => $consultation->price,
    //         'platform_fee' => $fee,
    //         'total' => $consultation->price ,
    //         'status' => 'pending'
    //     ]);

    //     return response()->json([
    //         'invoice_url' => $result['invoice_url']
    //     ]);
    // }

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
        $method = PaymentMethod::where('code','xendit')->first();
    
        if ($status == 'PAID') {

            DB::beginTransaction();
        
            try{
        
                $payment = Payments::lockForUpdate()->find($payment->id);
                $consult = Consultation::lockForUpdate()->find($payment->consultation_id);
        
                // webhook retry safety
                if($consult->payment_status == 'paid'){
                    DB::commit();
                    return response()->json(['success'=>true]);
                }

                // kunci ketersediaan mentor
                $mentor = Mentor::lockForUpdate()->find($consult->mentor_id);
        
                if(!$mentor){
                    DB::rollBack();
                    return response()->json(['error'=>'mentor not found']);
                }
                
                // cek apakah slot masih tersedia
                if(
                    $mentor->current_consultation_id && 
                    $mentor->current_consultation_id != $consult->id
                ){
                    // terlambat bayar
                    $payment->update(['status'=>'failed']);
                
                    DB::commit();
                
                    return response()->json([
                        'success'=>true,
                        'message'=>'slot already taken by another user'
                    ]);
                }
        
                // PAYMENT SUCCESS
                $payment->update([
                    'status'=>'paid',
                    'paid_at'=>now(),
                    'payment_method_id'=>$method->id ?? null
                ]);
        
                // kunci slot ke mentor
                $mentor->update([
                    'current_consultation_id'=>$consult->id
                ]);
        
                // auto mulai konsultasi
                $consult->update([
                    'payment_status'=>'paid',
                    'status'=>'active',
                    'started_at'=>now(),
                    'ended_at'=>now()->addMinutes($consult->duration_minutes + 10)
                ]);
        
                DB::commit();
        
            } catch(\Exception $e){
                DB::rollBack();
                return response()->json(['error'=>$e->getMessage()]);
            }
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
        $consult = Consultation::with('mentor')
            ->where('order_number',$orderNumber)
            ->first();
    
        if(!$consult){
            return response()->json([
                'success'=>false,
                'message'=>'Consultation not found'
            ],404);
        }
    
        $userId = auth()->id();
    
        // cek customer
        if($consult->customer_user_id == $userId){
            return response()->json([
                'success'=>true,
                'role'=>'customer',
                'status'=>$consult->status,
                'payment_status'=>$consult->payment_status
            ]);
        }
    
        // cek mentor
        if($consult->mentor && $consult->mentor->user_id == $userId){
            return response()->json([
                'success'=>true,
                'role'=>'mentor',
                'status'=>$consult->status,
                'payment_status'=>$consult->payment_status
            ]);
        }
    
        // bukan keduanya
        return response()->json([
            'success'=>false,
            'message'=>'Not allowed'
        ],403);
    }
    
    // Tanpa verify
    // public function paymentManual(Request $request, $consultationId)
    // {
    //     $request->validate([
    //         'proof_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
    //         'bank_id' => 'required|exists:mst_bank,id_bank',
    //         'bank_account' => 'required|string|max:50',
    //         'bank_holder_name' => 'required|string|max:100'
    //     ]);

    //     DB::beginTransaction();

    //     try{

    //         $consult = Consultation::lockForUpdate()->findOrFail($consultationId);

    //         if ($consult->payment_status == 'paid') {
    //             DB::rollBack();
    //             return response()->json([
    //                 'message'=>'Already paid'
    //             ],400);
    //         }

    //         // 🔴 CEK SLOT MENTOR (INTI SYSTEM)
    //         $mentorBusy = Consultation::where('mentor_id',$consult->mentor_id)
    //             ->where('payment_status','paid')
    //             ->where(function($q){
    //                 $q->whereNull('started_at')
    //                 ->orWhereRaw("
    //                     DATE_ADD(started_at, INTERVAL duration_minutes + 30 MINUTE) > NOW()
    //                 ");
    //             })
    //             ->lockForUpdate()
    //             ->exists();

    //         if ($mentorBusy) {
    //             DB::rollBack();
    //             return response()->json([
    //                 'status'=>false,
    //                 'message'=>'Mentor sedang ada session. Slot sudah diambil user lain.'
    //             ],409);
    //         }

    //         $payment = Payments::where('consultation_id',$consult->id)->first();

    //         $fee = Fee::where('key_name','app_fee')->value('value') ?? 0;

    //         if (!$payment) {
    //             $payment = Payments::create([
    //                 'consultation_id' => $consult->id,
    //                 'payment_method_id' => 1,
    //                 'service_price' => $consult->price,
    //                 'platform_fee' => $fee,
    //                 'total' => $consult->price,
    //                 'status' => 'paid',
    //                 'paid_at' => now()
    //             ]);
    //         }

    //         $file = $request->file('proof_image');
    //         $filename = 'manual_'.$consult->id.'_'.time().'.'.$file->getClientOriginalExtension();
    //         $file->move(public_path('uploads/manual_payments'), $filename);

    //         $payment->update([
    //             'payment_method_id'=>1,
    //             'status'=>'paid',
    //             'paid_at'=>now()
    //         ]);

    //         PaymentManual::updateOrCreate(
    //             ['payment_id'=>$payment->id],
    //             [
    //                 'bank_id'=>$request->bank_id,
    //                 'bank_account'=>$request->bank_account,
    //                 'bank_holder_name'=>$request->bank_holder_name,
    //                 'proof_image'=>$filename
    //             ]
    //         );

    //         // 🔥 LOCK SLOT MENTOR
    //         $consult->update([
    //             'payment_status'=>'paid',
    //             'status'=>'waiting'
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'success'=>true,
    //             'message'=>'Pembayaran berhasil & slot terkunci',
    //             'data'=>$payment
    //         ]);

    //     } catch(\Exception $e){
    //         DB::rollBack();
    //         return response()->json([
    //             'error'=>$e->getMessage()
    //         ],500);
    //     }
    // }
    
}
