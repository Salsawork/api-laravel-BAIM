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
            'service_type_id' => 'required|exists:service_types,id',
            'topic_category_id' => 'required|exists:topic_categories,id',
            'departure_date' => 'nullable|date'
        ]);
    
        $user = auth()->user();
    
        DB::beginTransaction();
    
        try {
    
            $mentor = Mentor::where('id', $request->mentor_id)
                ->lockForUpdate()
                ->first();
    
            if (!$mentor) {
                return response()->json([
                    'status'=>false,
                    'message'=>'Mentor not found'
                ],404);
            }
    
            // =============================
            // VALIDASI USER TYPE
            // =============================
            if ($mentor->user_type_id == 1) { // MUTHOWIF
                if (!$request->departure_date) {
                    return response()->json([
                        'status'=>false,
                        'message'=>'Departure date required for muthowif'
                    ],422);
                }
            } else {
                // konsultan tidak boleh isi
                $request->merge(['departure_date'=>null]);
            }
    
            // MENTOR ONLINE CHECK
            if (!$mentor->is_online) {
                return response()->json([
                    'status'=>false,
                    'message'=>'Mentor offline'
                ],409);
            }
    
            // MENTOR BUSY CHECK
            // $busy = Consultation::where('mentor_id',$mentor->id)
            //     ->whereIn('status',['pending','active'])
            //     ->exists();
            $check = Consultation::where('mentor_id',$mentor->id)
            ->whereNotNull('started_at')
            ->whereRaw('DATE_ADD(started_at, INTERVAL duration_minutes MINUTE) > NOW()')
            ->exists();
            
            if ($check) {
                return response()->json([
                    'status'=>false,
                    'message'=>'Mentor is still in the consultation session.'
                ],409);
            }
    
            // SERVICE
            $service = MentorService::where('mentor_id',$mentor->id)
                ->where('service_type_id',$request->service_type_id)
                ->first();
    
            if (!$service) {
                return response()->json([
                    'status'=>false,
                    'message'=>'Service not available'
                ],404);
            }
    
            // CREATE CONSULTATION 
            $orderNumber = 'BAIM-'.time().rand(100,999);
    
            $duration = $service->duration_minutes ?? 60;
    
            $consultation = Consultation::create([
                'order_number' => $orderNumber,
                'customer_user_id' => $user->id,
                'mentor_id' => $mentor->id,
                'service_type_id' => $request->service_type_id,
                'topic_category_id' => $request->topic_category_id,
                'departure_date' => $request->departure_date,
                'price' => $service->price,
                'duration_minutes' => $duration,
                'status' => 'pending',
                'payment_status' => 'waiting'
            ]);
    
            DB::commit();
    
            return response()->json([
                'status'=>true,
                'message'=>'Booking created, waiting payment',
                'data'=>[
                    'consultation_id'=>$consultation->id,
                    'order_number'=>$consultation->order_number,
                    'price'=>$consultation->price
                ]
            ]);
    
        } catch (\Exception $e) {
    
            DB::rollBack();
    
            return response()->json([
                'status'=>false,
                'message'=>'Booking failed',
                'error'=>$e->getMessage()
            ],500);
        }
    }

    public function testPayment(Request $request)
    {
        $request->validate([
            'consultation_id' => 'required|exists:consultations,id'
        ]);

        DB::beginTransaction();

        try {

            $consult = Consultation::where('id',$request->consultation_id)
                ->lockForUpdate()
                ->first();

            if (!$consult) {
                return response()->json([
                    'status'=>false,
                    'message'=>'Consultation not found'
                ],404);
            }

            if ($consult->payment_status == 'paid') {
                return response()->json([
                    'status'=>false,
                    'message'=>'Already paid'
                ],400);
            }

            // =============================
            // UPDATE PAYMENT SUCCESS
            // =============================
            $consult->update([
                'payment_status' => 'paid',
                'status' => 'active',
                'started_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'status'=>true,
                'message'=>'Payment success (fake)',
                'data'=>[
                    'consultation_id'=>$consult->id,
                    'status'=>$consult->status
                ]
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status'=>false,
                'message'=>'Payment failed',
                'error'=>$e->getMessage()
            ],500);
        }
    }


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
