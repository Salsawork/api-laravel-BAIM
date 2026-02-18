<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;
use App\Models\Consultation;
use App\Models\Payments;

use App\Models\Mentor;
use App\Models\MentorService;
use App\Models\Schedule;

class ConsultationController extends Controller
{
    public function booking(Request $request)
    {
        $request->validate([
            'mentor_id' => 'required|exists:mentors,id',
            'service_type_id' => 'nullable|exists:service_types,id',
            'topic_category_id' => 'required|exists:topic_categories,id',
    
            // khusus muthowif
            'departure_date' => 'nullable|date',
            'people_count' => 'nullable|integer|min:1',
            'package_price' => 'nullable|numeric|min:0'
        ]);
    
        $user = auth()->user();
    
        DB::beginTransaction();
    
        try {
    
            $mentor = Mentor::lockForUpdate()->find($request->mentor_id);
    
            if(!$mentor){
                return response()->json(['status'=>false,'message'=>'Mentor not found'],404);
            }
    
            if(!$mentor->is_online){
                return response()->json(['status'=>false,'message'=>'Mentor offline'],409);
            }
    
            // cek mentor busy
            if(!is_null($mentor->current_consultation_id)){
                return response()->json([
                    'status'=>false,
                    'message'=>'Mentor sedang melayani user lain'
                ],409);
            }            
    
            $orderNumber = 'BAIM-'.time().rand(100,999);
    
            // =========================================
            // CASE 1 : MUTHOWIF (FREE CHAT)
            // =========================================
            if($mentor->user_type_id == 1){
    
                if(!$request->departure_date || !$request->people_count || !$request->package_price){
                    return response()->json([
                        'status'=>false,
                        'message'=>'Isi tanggal, jumlah orang, dan harga paket'
                    ],422);
                }
    
                $consult = Consultation::create([
                    'order_number'=>$orderNumber,
                    'customer_user_id'=>$user->id,
                    'mentor_id'=>$mentor->id,
                    'service_type_id'=>1, // chat only
                    'topic_category_id'=>$request->topic_category_id,
                    'departure_date'=>$request->departure_date,
    
                    'people_count'=>$request->people_count,
                    'package_price'=>$request->package_price,
                    'total_price'=>$request->package_price * $request->people_count,
                    'price'=>0,
    
                    'duration_minutes'=>60,
                    'payment_status'=>'free',
                    'status'=>'active',
                    'started_at'=>now(),
                    'ended_at'=>now()->addMinutes(60)
                ]);
    
                // lock mentor
                $mentor->update([
                    'current_consultation_id'=>$consult->id
                ]);
    
                DB::commit();
    
                return response()->json([
                    'status'=>true,
                    'message'=>'Chat muthowif dimulai (gratis)',
                    'data'=>[
                        'order_number'=>$consult->order_number,
                        'consultation_id'=>$consult->id
                    ]
                ]);
            }
    
            // =========================================
            // CASE 2 : KONSULTAN BAYAR
            // =========================================
            $service = MentorService::where('mentor_id',$mentor->id)
                ->where('service_type_id',$request->service_type_id)
                ->first();
    
            if(!$service){
                return response()->json([
                    'status'=>false,
                    'message'=>'Service tidak tersedia'
                ],404);
            }
    
            $consult = Consultation::create([
                'order_number'=>$orderNumber,
                'customer_user_id'=>$user->id,
                'mentor_id'=>$mentor->id,
                'service_type_id'=>$request->service_type_id,
                'topic_category_id'=>$request->topic_category_id,
    
                'price'=>$service->price,
                'duration_minutes'=>$service->duration_minutes ?? 60,
    
                'status'=>'pending',
                'payment_status'=>'waiting',
                'expired_at'=>now()->addMinutes(10)
            ]);
    
            DB::commit();
    
            return response()->json([
                'status'=>true,
                'message'=>'Booking berhasil, lanjut pembayaran',
                'data'=>[
                    'consultation_id'=>$consult->id,
                    'order_number'=>$consult->order_number,
                    'price'=>$consult->price
                ]
            ]);
    
        } catch (\Exception $e){
            DB::rollBack();
            return response()->json([
                'status'=>false,
                'message'=>$e->getMessage()
            ],500);
        }
    }
    
    public function joinRoom($orderNumber)
    {
        DB::beginTransaction();
    
        try{
    
            $consult = Consultation::lockForUpdate()
                ->with('mentor')
                ->where('order_number',$orderNumber)
                ->firstOrFail();
    
            // HANYA UNTUK CALL & VIDEO
            if(!in_array($consult->service_type_id,[2,3])){
                DB::rollBack();
                return response()->json([
                    'message'=>'Gunakan join chat untuk layanan chat'
                ],403);
            }
    
            // HARUS SUDAH BAYAR
            if ($consult->payment_status != 'paid') {
                DB::rollBack();
                return response()->json(['message'=>'Belum bayar'],403);
            }
    
            // SESSION HARUS ACTIVE
            if ($consult->status != 'active') {
                DB::rollBack();
                return response()->json(['message'=>'Session not active'],403);
            }
    
            // SESSION EXPIRED?
            if ($consult->ended_at && now()->gt($consult->ended_at)) {
                DB::rollBack();
                return response()->json(['message'=>'Session expired'],403);
            }
    
            // VALIDASI PESERTA
            $userId = auth()->id();
    
            if (!$consult->mentor) {
                DB::rollBack();
                return response()->json(['message'=>'Mentor not found'],404);
            }
    
            if ($consult->customer_user_id != $userId && $consult->mentor->user_id != $userId) {
                DB::rollBack();
                return response()->json(['message'=>'Not allowed'],403);
            }
    
            // GENERATE CHANNEL
            if (!$consult->agora_channel) {
                $consult->agora_channel = 'consult_'.$consult->id;
                $consult->save();
            }
    
            // GENERATE RTC TOKEN
            $token = app(\App\Services\AgoraService::class)
                ->buildToken($consult->agora_channel, $userId, 7200);
    
            DB::commit();
    
            return response()->json([
                'success'=>true,
                'channel'=>$consult->agora_channel,
                'token'=>$token,
                'service_type_id'=>$consult->service_type_id,
                'started_at'=>$consult->started_at,
                'ended_at'=>$consult->ended_at
            ]);
    
        } catch(\Exception $e){
            DB::rollBack();
            return response()->json(['error'=>$e->getMessage()],500);
        }
    }
    
    
    public function joinChat($orderNumber)
    {
        $consult = Consultation::where('order_number',$orderNumber)
            ->firstOrFail();

        // harus chat service
        if($consult->service_type_id != 1){
            return response()->json(['message'=>'Bukan layanan chat'],403);
        }

        // harus sudah bayar
        if($consult->payment_status != 'paid'){
            return response()->json(['message'=>'Belum bayar'],403);
        }

        // session harus aktif
        if($consult->status != 'active'){
            return response()->json(['message'=>'Session not active'],403);
        }

        // session expired?
        if($consult->ended_at && now()->gt($consult->ended_at)){
            return response()->json(['message'=>'Session expired'],403);
        }

        $userId = auth()->id();

        // validasi peserta
        $mentorUserId = $consult->mentor->user_id;

        if($userId != $consult->customer_user_id && $userId != $mentorUserId){
            return response()->json(['message'=>'Not allowed'],403);
        }

        // token chat
        $token = app(\App\Services\AgoraChatService::class)
            ->buildToken($userId);

        return response()->json([
            'app_id'=>config('services.agora.app_id'),
            'token'=>$token,
            'channel'=>'chat_'.$consult->id,
            'uid'=>$userId,
            'ended_at'=>$consult->ended_at
        ]);
    }


    public function endSession($orderNumber)
    {
        DB::beginTransaction();

        try{

            $consult = Consultation::lockForUpdate()
                ->where('order_number',$orderNumber)
                ->firstOrFail();

            // validasi status active
            if($consult->status !== 'active'){
                DB::rollBack();
                return response()->json([
                    'status'=>false,
                    'message'=>'Session not active'
                ],400);
            }

            // VALIDASI USER (MENTOR ATAU CUSTOMER)
            $userId = auth()->id();
            if($consult->customer_user_id != $userId && $consult->mentor->user_id != $userId){
                DB::rollBack();
                return response()->json(['message'=>'Not allowed'],403);
            }

            // update status completed
            $consult->update([
                'status'=>'completed',
                'ended_at'=>now()
            ]);

            // FREE MENTOR SLOT
            Mentor::where('id',$consult->mentor_id)
                ->lockForUpdate()
                ->update([
                    'current_consultation_id'=>null
                ]);

            DB::commit();

            return response()->json([
                'success'=>true,
                'message'=>'Session ended'
            ]);

        } catch(\Exception $e){
            DB::rollBack();
            return response()->json(['error'=>$e->getMessage()],500);
        }
    }

    // History consultation dari sisi customer
    public function historyCustomer(Request $request)
    {
        $userId = auth()->id();

        $query = Consultation::with([
            'mentor:id,user_id,full_name',
            'mentor.user:id,profile_photo_path',
            'service:id,name',
            'payment:id,consultation_id,status,paid_at,total'
        ])
        ->where('customer_user_id',$userId);

        // filter status opsional
        if($request->status){
            $query->where('status',$request->status);
        }

        // urutan
        $data = $query
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json([
            'success'=>true,
            'total'=>$data->total(),
            'data'=>$data->items(),
            'current_page'=>$data->currentPage(),
            'last_page'=>$data->lastPage()
        ]);
    }

    // History consultation dari sisi mentor
    public function historyMentor(Request $request)
    {
        $userId = auth()->id();

        $mentor = Mentor::where('user_id',$userId)->first();

        if(!$mentor){
            return response()->json([
                'success'=>false,
                'message'=>'Mentor not found'
            ],404);
        }

        $query = Consultation::with([
            'customer:id,name,email,profile_photo_path',
            'service:id,name',
            'payment:id,consultation_id,status,paid_at,total'
        ])
        ->where('mentor_id',$mentor->id);

        // filter status
        if($request->status){
            $query->where('status',$request->status);
        }

        $data = $query
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json([
            'success'=>true,
            'total'=>$data->total(),
            'data'=>$data->items(),
            'current_page'=>$data->currentPage(),
            'last_page'=>$data->lastPage()
        ]);
    }

    // public function testPayment(Request $request)
    // {
    //     $request->validate([
    //         'consultation_id' => 'required|exists:consultations,id'
    //     ]);

    //     DB::beginTransaction();

    //     try {

    //         $consult = Consultation::where('id',$request->consultation_id)
    //             ->lockForUpdate()
    //             ->first();

    //         if (!$consult) {
    //             return response()->json([
    //                 'status'=>false,
    //                 'message'=>'Consultation not found'
    //             ],404);
    //         }

    //         if ($consult->payment_status == 'paid') {
    //             return response()->json([
    //                 'status'=>false,
    //                 'message'=>'Already paid'
    //             ],400);
    //         }

    //         // =============================
    //         // UPDATE PAYMENT SUCCESS
    //         // =============================
    //         $consult->update([
    //             'payment_status' => 'paid',
    //             'status' => 'active',
    //             'started_at' => now()
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'status'=>true,
    //             'message'=>'Payment success (fake)',
    //             'data'=>[
    //                 'consultation_id'=>$consult->id,
    //                 'status'=>$consult->status
    //             ]
    //         ]);

    //     } catch (\Exception $e) {

    //         DB::rollBack();

    //         return response()->json([
    //             'status'=>false,
    //             'message'=>'Payment failed',
    //             'error'=>$e->getMessage()
    //         ],500);
    //     }
    // }

    // dengan schedule
    // public function booking(Request $request)
    // {
    //     $request->validate([
    //         'mentor_id' => 'required|exists:mentors,id',
    //         'service_type_id' => 'required|exists:service_types,id',
    //         'topic_category_id' => 'required|exists:topic_categories,id',
    //     ]);

    //     $user = auth()->user();

    //     DB::beginTransaction();

    //     try {

    //         // ==========================
    //         // LOCK mentor row
    //         // ==========================

    //         $mentor = Mentor::where('id', $request->mentor_id)
    //             ->lockForUpdate()
    //             ->first();

    //         if (!$mentor) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Mentor not found'
    //             ], 404);
    //         }
    //         //validasi status mentor

    //         $busy = Consultation::where('mentor_id', $mentor->id)
    //             ->whereIn('status', ['pending', 'active'])
    //             ->exists();

    //         if ($busy) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Mentor currently busy'
    //             ], 409);
    //         }

    //         // Validasi waktu jeda dengan konsultasi sebelumnya

    //         $lastSession = Consultation::where('mentor_id', $mentor->id)
    //             ->where('status', 'completed')
    //             ->orderByDesc('ended_at')
    //             ->first();

    //         if ($lastSession) {

    //             $cooldownUntil = Carbon::parse($lastSession->ended_at)
    //                 ->addMinutes($mentor->cooldown_minutes);

    //             if (now()->lt($cooldownUntil)) {
    //                 return response()->json([
    //                     'status' => false,
    //                     'message' => 'Mentor still in cooldown time'
    //                 ], 409);
    //             }
    //         }

    //         // Validasi Schedue availability

    //         $nowDate = now()->toDateString();
    //         $nowTime = now()->toTimeString();

    //         $schedule = Schedule::where('mentor_id', $mentor->id)
    //             ->where('date', $nowDate)
    //             ->where('started_at', '<=', $nowTime)
    //             ->where('ended_at', '>=', $nowTime)
    //             ->where('is_booked', 0)
    //             ->lockForUpdate()
    //             ->first();

    //         if (!$schedule) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Mentor not available at this time'
    //             ], 409);
    //         }

    //         // Ambil detail service call/VC/Chat

    //         $service = MentorService::where('mentor_id', $mentor->id)
    //             ->where('service_type_id', $request->service_type_id)
    //             ->first();

    //         if (!$service) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Service not available'
    //             ], 404);
    //         }

    //         // Create Consultasi 

    //         $orderNumber = 'BAIM-' . time() . '-' . rand(100, 999);

    //         $consultation = Consultation::create([
    //             'order_number' => $orderNumber,
    //             'customer_user_id' => $user->id,
    //             'mentor_id' => $mentor->id,
    //             'service_type_id' => $request->service_type_id,
    //             'topic_category_id' => $request->topic_category_id,
    //             'schedule_id' => $schedule->id,
    //             'price' => $service->price,
    //             'duration_minutes' => $service->duration_minutes,
    //             'status' => 'pending',
    //             'payment_status' => 'waiting'
    //         ]);

    //         // Mengunci Schedule

    //         $schedule->update([
    //             'is_booked' => 1
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Consultation booked',
    //             'data' => [
    //                 'consultation_id' => $consultation->id,
    //                 'order_number' => $consultation->order_number,
    //                 'price' => $consultation->price,
    //                 'duration' => $consultation->duration_minutes
    //             ]
    //         ]);

    //     } catch (\Exception $e) {

    //         DB::rollBack();

    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Booking failed',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

}
