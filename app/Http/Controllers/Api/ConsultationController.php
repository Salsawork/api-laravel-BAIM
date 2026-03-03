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
    // public function booking(Request $request)
    // {
    //     $request->validate([
    //         'mentor_id' => 'required|exists:mentors,id',
    //         'service_type_id' => 'nullable|exists:service_types,id',
    //         'topic_category_id' => 'required|exists:topic_categories,id',
    
    //         // khusus muthowif
    //         'departure_date' => 'nullable|date',
    //         'people_count' => 'nullable|integer|min:1',
    //         'package_price' => 'nullable|numeric|min:0'
    //     ]);
    
    //     $user = auth()->user();
    
    //     DB::beginTransaction();
    
    //     try {
    
    //         $mentor = Mentor::lockForUpdate()->find($request->mentor_id);
    
    //         if(!$mentor){
    //             return response()->json(['status'=>false,'message'=>'Mentor not found'],404);
    //         }
    
    //         if(!$mentor->is_online){
    //             return response()->json(['status'=>false,'message'=>'Mentor offline'],409);
    //         }
    
    //         $existingPending = Consultation::where('mentor_id',$mentor->id)
    //             ->whereIn('status',['pending','active'])
    //             ->exists();

    //         if($existingPending){
    //         return response()->json([
    //             'status'=>false,
    //             'message'=>'Mentor sedang ada booking aktif'
    //         ],409);
    //         }

    //         // cek mentor busy
    //         if(!is_null($mentor->current_consultation_id)){
    //             return response()->json([
    //                 'status'=>false,
    //                 'message'=>'Mentor sedang melayani user lain'
    //             ],409);
    //         }            
    
    //         $orderNumber = 'BAIM-'.time().rand(100,999);
    
    //         // CASE 1 : MUTHOWIF (FREE CHAT)
    //         if($mentor->user_type_id == 1){
    
    //             if(!$request->departure_date || !$request->people_count || !$request->package_price){
    //                 return response()->json([
    //                     'status'=>false,
    //                     'message'=>'Isi tanggal, jumlah orang, dan harga paket'
    //                 ],422);
    //             }
    
    //             $consult = Consultation::create([
    //                 'order_number'=>$orderNumber,
    //                 'customer_user_id'=>$user->id,
    //                 'mentor_id'=>$mentor->id,
    //                 'service_type_id'=>1, // chat only
    //                 'topic_category_id'=>$request->topic_category_id,
    //                 'departure_date'=>$request->departure_date,
    
    //                 'people_count'=>$request->people_count,
    //                 'package_price'=>$request->package_price,
    //                 'total_price'=>$request->package_price * $request->people_count,
    //                 'price'=>0,
    
    //                 'duration_minutes'=>60,
    //                 'payment_status'=>'paid',
    //                 'status'=>'active',
    //                 'started_at'=>now(),
    //                 'ended_at'=>now()->addMinutes(60)
    //             ]);
    
    //             // lock mentor
    //             $mentor->update([
    //                 'current_consultation_id'=>$consult->id
    //             ]);
    
    //             DB::commit();
    
    //             return response()->json([
    //                 'status'=>true,
    //                 'message'=>'Chat muthowif dimulai (gratis)',
    //                 'data'=>[
    //                     'order_number'=>$consult->order_number,
    //                     'consultation_id'=>$consult->id
    //                 ]
    //             ]);
    //         }
    
    //         // CASE 2 : KONSULTAN BAYAR
    //         $service = MentorService::where('mentor_id',$mentor->id)
    //             ->where('service_type_id',$request->service_type_id)
    //             ->first();
    
    //         if(!$service){
    //             return response()->json([
    //                 'status'=>false,
    //                 'message'=>'Service tidak tersedia'
    //             ],404);
    //         }
    
    //         $consult = Consultation::create([
    //             'order_number'=>$orderNumber,
    //             'customer_user_id'=>$user->id,
    //             'mentor_id'=>$mentor->id,
    //             'service_type_id'=>$request->service_type_id,
    //             'topic_category_id'=>$request->topic_category_id,
    
    //             'price'=>$service->price,
    //             'duration_minutes'=>$service->duration_minutes ?? 60,
    
    //             'status'=>'pending',
    //             'payment_status'=>'waiting',
    //             'expired_at'=>now()->addMinutes(10)
    //         ]);
    
    //         DB::commit();
    
    //         return response()->json([
    //             'status'=>true,
    //             'message'=>'Booking berhasil, lanjut pembayaran',
    //             'data'=>[
    //                 'consultation_id'=>$consult->id,
    //                 'order_number'=>$consult->order_number,
    //                 'price'=>$consult->price
    //             ]
    //         ]);
    
    //     } catch (\Exception $e){
    //         DB::rollBack();
    //         return response()->json([
    //             'status'=>false,
    //             'message'=>$e->getMessage()
    //         ],500);
    //     }
    // }
    public function bookingRealtime(Request $request)
    {
        $request->validate([
            'mentor_id'=>'required|exists:mentors,id',
            'service_type_id'=>'required|exists:service_types,id',
            'topic_category_id'=>'required|exists:topic_categories,id',
            'duration_hours'=>'required|integer|min:1|max:6'
        ]);

        $user = auth()->user();
        DB::beginTransaction();

        try{

            $mentor = Mentor::lockForUpdate()->find($request->mentor_id);
            
            if(!$mentor){
                return response()->json(['status'=>false,'message'=>'Mentor not found'],404);
            }

            if(!$mentor->is_online){
                return response()->json(['status'=>false,'message'=>'Mentor offline'],409);
            }

            if($mentor->current_consultation_id){
                return response()->json([
                    'status'=>false,
                    'message'=>'Mentor sedang melayani user lain'
                ],409);
            }

            // CEK SCHEDULE HARI INI

            $mentorTz = $mentor->timezone ?? 'Asia/Jakarta';
            $nowUtc = now()->utc();
            
            // ambil semua schedule aktif mentor
            $schedules = Schedule::where('mentor_id', $mentor->id)
                ->where('is_active', 1)
                ->get();
            
            $validSchedule = null;
            
            foreach ($schedules as $schedule) {
            
                // convert schedule ke UTC berdasarkan timezone mentor
                $scheduleStartUtc = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $schedule->date.' '.$schedule->start_time,
                    $mentorTz
                )->utc();
            
                $scheduleEndUtc = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $schedule->date.' '.$schedule->end_time,
                    $mentorTz
                )->utc();
            
                if ($nowUtc->between($scheduleStartUtc, $scheduleEndUtc)) {
                    $validSchedule = [
                        'start' => $scheduleStartUtc,
                        'end'   => $scheduleEndUtc,
                        'model' => $schedule
                    ];
                    break;
                }
            }
            
            if (!$validSchedule) {
                return response()->json([
                    'status'=>false,
                    'message'=>'Diluar jam kerja mentor'
                ],422);
            }
            
            $scheduleStart = $validSchedule['start'];
            $scheduleEnd   = $validSchedule['end'];
            $scheduleModel = $validSchedule['model'];

            // CEK DURASI TIDAK MELEBIHI SCHEDULE
            $duration = $request->duration_hours;
            $endSessionUtc = $nowUtc->copy()->addHours($duration);

            if($endSessionUtc->gt($scheduleEnd)){
                return response()->json([
                    'status'=>false,
                    'message'=>'Durasi melebihi jam schedule mentor'
                ],422);
            }

            // CEK TABRAK FUTURE BOOKING
            $future = Consultation::where('mentor_id',$mentor->id)
                ->where(function($q){
                    $q->where('status','active')
                    ->orWhere(function($q2){
                        $q2->where('status','pending')
                            ->where('payment_status','paid');
                    });
                })
                ->whereNotNull('scheduled_start')
                ->where('scheduled_start','>',now())
                ->orderBy('scheduled_start')
                ->first();

            if($future){
                $futureStart = Carbon::parse($future->scheduled_start);

                if($endSession > $futureStart){
                    return response()->json([
                        'status'=>false,
                        'message'=>'Mentor memiliki booking terjadwal setelah ini'
                    ],409);
                }
            }

            // HITUNG HARGA
            if($mentor->user_type_id == 1){
                // MUTHOWIF
                $pricePerHour = $scheduleModel->price;
            }else{
                // KONSULTAN
                $service = MentorService::where('mentor_id',$mentor->id)
                    ->where('service_type_id',$request->service_type_id)
                    ->first();

                if(!$service){
                    return response()->json([
                        'status'=>false,
                        'message'=>'Service tidak tersedia'
                    ],404);
                }

                $pricePerHour = $service->price;
            }

            $total = $pricePerHour * $duration;
            $minutes = $duration * 60;

            $order = 'BAIM-'.time().rand(100,999);

            $consult = Consultation::create([
                'order_number'=>$order,
                'customer_user_id'=>$user->id,
                'mentor_id'=>$mentor->id,
                'service_type_id'=>$request->service_type_id,
                'topic_category_id'=>$request->topic_category_id,

                'duration_hours'=>$duration,
                'duration_minutes'=>$minutes,

                'price'=>$pricePerHour,
                'total_price'=>$total,

                'status'=>'pending',
                'payment_status'=>'waiting',
                'expired_at'=>now()->addMinutes(10)
            ]);

            DB::commit();

            return response()->json([
                'status'=>true,
                'message'=>'Booking realtime berhasil',
                'data'=>[
                    'consultation_id'=>$consult->id,
                    'order_number'=>$consult->order_number,
                    'price_per_hour'=>$pricePerHour,
                    'duration_hours'=>$duration,
                    'total_price'=>$total
                ]
            ]);

        }catch(\Exception $e){
            DB::rollBack();
            return response()->json([
                'status'=>false,
                'message'=>$e->getMessage()
            ],500);
        }
    }

    public function bookingScheduled(Request $request)
    {
        $request->validate([
            'mentor_id'=>'required|exists:mentors,id',
            'schedule_id'=>'required|exists:mentor_schedules,id',
            'service_type_id'=>'required|exists:service_types,id',
            'topic_category_id'=>'required|exists:topic_categories,id',
            'date'=>'required|date',
            'start_time'=>'required',
            'duration_hours'=>'required|integer|min:1|max:12'
        ]);
    
        $user = auth()->user();
        DB::beginTransaction();
    
        try{
    
            $mentor = Mentor::lockForUpdate()->find($request->mentor_id);
            
            if(!$mentor){
                return response()->json([
                    'status'=>false,
                    'message'=>'Mentor tidak ditemukan'
                ],404);
            }
    
            $schedule = Schedule::where('id',$request->schedule_id)
                ->where('mentor_id',$mentor->id)
                ->where('is_active',1)
                ->first();
    
            if(!$schedule){
                return response()->json([
                    'status'=>false,
                    'message'=>'Schedule tidak ditemukan'
                ],404);
            }
    
            // datetime start end
            $start = Carbon::parse($request->date.' '.$request->start_time);
            if($start < now()){
                return response()->json([
                    'status'=>false,
                    'message'=>'Tidak bisa booking waktu yang sudah lewat'
                ],422);
            }

            $end   = (clone $start)->addHours($request->duration_hours);
    
            $scheduleStart = Carbon::parse($schedule->date.' '.$schedule->start_time);
            $scheduleEnd   = Carbon::parse($schedule->date.' '.$schedule->end_time);
    
            if($start < $scheduleStart || $end > $scheduleEnd){
                return response()->json([
                    'status'=>false,
                    'message'=>'Diluar jam schedule mentor'
                ],422);
            }
    
            // jika mentor online & jam sedang berjalan → pakai realtime
            if($mentor->is_online){
                $now = now();
                if($now >= $scheduleStart && $now <= $scheduleEnd){
                    return response()->json([
                        'status'=>false,
                        'message'=>'Mentor sedang online, gunakan realtime booking'
                    ],422);
                }
            }
    
            // CEK BENTROK BOOKING
            $conflict = Consultation::where('mentor_id',$mentor->id)
                ->where(function($q){
                    $q->where('status','active')
                    ->orWhere(function($q2){
                        $q2->where('status','pending')
                            ->where('payment_status','paid');
                    });
                })
                ->whereNotNull('scheduled_start')
                ->where(function($q) use ($start,$end){
                    $q->whereBetween('scheduled_start',[$start,$end])
                    ->orWhereBetween('scheduled_end',[$start,$end])
                    ->orWhere(function($q2) use ($start,$end){
                        $q2->where('scheduled_start','<=',$start)
                           ->where('scheduled_end','>=',$end);
                    });
                })
                ->exists();
    
            if($conflict){
                return response()->json([
                    'status'=>false,
                    'message'=>'Slot sudah dibooking user lain'
                ],409);
            }
    
            // HITUNG HARGA
            if($mentor->user_type_id == 1){
                // MUTHOWIF
                $pricePerHour = $schedule->price;
            }else{
                // KONSULTAN
                $service = MentorService::where('mentor_id',$mentor->id)
                    ->where('service_type_id',$request->service_type_id)
                    ->first();
    
                if(!$service){
                    return response()->json([
                        'status'=>false,
                        'message'=>'Service tidak tersedia'
                    ],404);
                }
    
                $pricePerHour = $service->price;
            }
    
            $duration = $request->duration_hours;
            $total    = $pricePerHour * $duration;
            $minutes  = $duration * 60;
    
            $order = 'BAIM-'.time().rand(100,999);
    
            $consult = Consultation::create([
                'order_number'=>$order,
                'customer_user_id'=>$user->id,
                'mentor_id'=>$mentor->id,
                'service_type_id'=>$request->service_type_id,
                'topic_category_id'=>$request->topic_category_id,
    
                'schedule_id'=>$schedule->id,
    
                'duration_hours'=>$duration,
                'duration_minutes'=>$minutes,
    
                'price'=>$pricePerHour,
                'total_price'=>$total,
    
                'scheduled_start'=>$start,
                'scheduled_end'=>$end,
    
                'status'=>'pending',
                'payment_status'=>'waiting',
                'expired_at'=>now()->addMinutes(10)
            ]);
    
            DB::commit();
    
            return response()->json([
                'status'=>true,
                'message'=>'Booking terjadwal berhasil',
                'data'=>[
                    'consultation_id'=>$consult->id,
                    'order_number'=>$consult->order_number,
                    'price_per_hour'=>$pricePerHour,
                    'duration_hours'=>$duration,
                    'total_price'=>$total,
                    'start'=>$start,
                    'end'=>$end
                ]
            ]);
    
        }catch(\Exception $e){
            DB::rollBack();
            return response()->json([
                'status'=>false,
                'message'=>$e->getMessage()
            ],500);
        }
    }

    public function getAvailableSlots($mentorId, Request $request)
    {
        $request->validate([
            'date'=>'required|date'
        ]);

        $mentor = Mentor::find($mentorId);
        if(!$mentor){
            return response()->json(['status'=>false,'message'=>'Mentor not found'],404);
        }


        $schedule = Schedule::where('mentor_id',$mentor->id)
            ->whereDate('date',$request->date)
            ->where('is_active',1)
            ->first();

        if(!$schedule){
            return response()->json([
                'status'=>false,
                'message'=>'Tidak ada schedule di tanggal ini'
            ]);
        }

        $start = Carbon::parse($schedule->date.' '.$schedule->start_time);
        $end   = Carbon::parse($schedule->date.' '.$schedule->end_time);

        $slots = [];

        while($start < $end){
            $slots[] = $start->format('H:i');
            $start->addHour();
        }

        // hapus slot yg sudah dibooking
        $booked = Consultation::where('mentor_id',$mentor->id)
            ->whereDate('scheduled_start',$request->date)
            ->where(function($q){
                $q->where('status','active')
                  ->orWhere(function($q2){
                      $q2->where('status','pending')
                         ->where('payment_status','paid');
                  });
            })
            ->pluck('scheduled_start')
            ->map(fn($d)=>Carbon::parse($d)->format('H:i'))
            ->toArray();

        $available = array_values(array_diff($slots,$booked));

        return response()->json([
            'status'=>true,
            'mentor'=>$mentor->full_name,
            'price_per_hour'=>$schedule->price,
            'slots'=>$available
        ]);
    }

    public function joinRoom($orderNumber)
    {
        DB::beginTransaction();
    
        try{
    
            $consult = Consultation::lockForUpdate()
                ->with('mentor')
                ->where('order_number',$orderNumber)
                ->firstOrFail();
    
            // hanya call/video
            if(!in_array($consult->service_type_id,[2,3])){
                DB::rollBack();
                return response()->json([
                    'message'=>'Gunakan join chat untuk layanan chat'
                ],403);
            }
    
            // harus sudah bayar
            if ($consult->payment_status != 'paid') {
                DB::rollBack();
                return response()->json(['message'=>'Belum bayar'],403);
            }
    
            // harus active
            if ($consult->status != 'active') {
                DB::rollBack();
                return response()->json(['message'=>'Session not active'],403);
            }
    
            // BELUM WAKTUNYA (scheduled)
            if($consult->started_at && now()->lt($consult->started_at)){
                DB::rollBack();
                return response()->json([
                    'message'=>'Session belum dimulai'
                ],403);
            }
    
            // expired
            if ($consult->ended_at && now()->gt($consult->ended_at)) {
                DB::rollBack();
                return response()->json(['message'=>'Session expired'],403);
            }
    
            // mentor slot masih valid?
            if($consult->status == 'active' && $consult->mentor->current_consultation_id != $consult->id){
                DB::rollBack();
                return response()->json([
                    'message'=>'Session sudah tidak valid'
                ],409);
            }
    
            // validasi user
            $userId = auth()->id();
    
            if ($consult->customer_user_id != $userId && $consult->mentor->user_id != $userId) {
                DB::rollBack();
                return response()->json(['message'=>'Not allowed'],403);
            }
    
            // generate channel
            if (!$consult->agora_channel) {
                $consult->agora_channel = 'consult_'.$consult->id;
                $consult->save();
            }
    
            // generate token agora
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
        $consult = Consultation::with('mentor')
            ->where('order_number',$orderNumber)
            ->firstOrFail();
    
        if($consult->service_type_id != 1){
            return response()->json(['message'=>'Bukan layanan chat'],403);
        }
    
        if($consult->payment_status != 'paid'){
            return response()->json(['message'=>'Belum bayar'],403);
        }
    
        if($consult->status != 'active'){
            return response()->json(['message'=>'Session not active'],403);
        }
    
        // belum waktunya (scheduled)
        if($consult->started_at && now()->lt($consult->started_at)){
            return response()->json(['message'=>'Session belum dimulai'],403);
        }
    
        // expired
        if($consult->ended_at && now()->gt($consult->ended_at)){
            return response()->json(['message'=>'Session expired'],403);
        }
    
        // slot valid?
        if($consult->status == 'active' && $consult->mentor->current_consultation_id != $consult->id){
            return response()->json(['message'=>'Session sudah tidak valid'],409);
        }
    
        $userId = auth()->id();
    
        if($userId != $consult->customer_user_id && $userId != $consult->mentor->user_id){
            return response()->json(['message'=>'Not allowed'],403);
        }
    
        $token = app(\App\Services\AgoraChatService::class)
            ->buildToken($userId);
    
        return response()->json([
            'app_id'=>config('services.agora.app_id'),
            'token'=>$token,
            'channel'=>'chat_'.$consult->id,
            'uid'=>$userId,
            'started_at'=>$consult->started_at,
            'ended_at'=>$consult->ended_at
        ]);
    }

    public function endSession($orderNumber)
    {
        DB::beginTransaction();
    
        try{
    
            $consult = Consultation::lockForUpdate()
                ->with('mentor')
                ->where('order_number',$orderNumber)
                ->firstOrFail();
    
            if($consult->status !== 'active'){
                DB::rollBack();
                return response()->json([
                    'status'=>false,
                    'message'=>'Session not active'
                ],400);
            }
    
            $userId = auth()->id();
    
            if($consult->customer_user_id != $userId && $consult->mentor->user_id != $userId){
                DB::rollBack();
                return response()->json(['message'=>'Not allowed'],403);
            }
    
            // completed
            $consult->update([
                'status'=>'completed',
                'ended_at'=>$consult->ended_at ?? now()
            ]);
    
            // free mentor slot
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
