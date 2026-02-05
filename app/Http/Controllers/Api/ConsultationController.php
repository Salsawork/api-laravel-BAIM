<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

use App\Models\Mentor;
use App\Models\MentorService;
use App\Models\Consultation;
use App\Models\Schedule;

class ConsultationController extends Controller
{

    public function booking(Request $request)
    {
        $request->validate([
            'mentor_id' => 'required|exists:mentors,id',
            'service_type_id' => 'required|exists:service_types,id',
            'topic_category_id' => 'required|exists:topic_categories,id',
        ]);

        $user = auth()->user();

        DB::beginTransaction();

        try {

            // 🔒 lock mentor row
            $mentor = Mentor::where('id', $request->mentor_id)
                ->lockForUpdate()
                ->first();

            if (!$mentor) {
                return response()->json([
                    'status' => false,
                    'message' => 'Mentor not found'
                ], 404);
            }

            // ❌ mentor offline
            if (!$mentor->is_online) {
                return response()->json([
                    'status' => false,
                    'message' => 'Mentor offline'
                ], 409);
            }

            // ❌ mentor sedang sesi
            $busy = Consultation::where('mentor_id', $mentor->id)
                ->whereIn('status', ['pending','active'])
                ->exists();

            if ($busy) {
                return response()->json([
                    'status' => false,
                    'message' => 'Mentor currently busy'
                ], 409);
            }

            // ambil service mentor
            $service = MentorService::where('mentor_id', $mentor->id)
                ->where('service_type_id', $request->service_type_id)
                ->first();

            if (!$service) {
                return response()->json([
                    'status' => false,
                    'message' => 'Service not available'
                ], 404);
            }

            // create consultation
            $orderNumber = 'BAIM-' . time() . rand(100,999);

            $consultation = Consultation::create([
                'order_number' => $orderNumber,
                'customer_user_id' => $user->id,
                'mentor_id' => $mentor->id,
                'service_type_id' => $request->service_type_id,
                'topic_category_id' => $request->topic_category_id,
                'price' => $service->price,
                'duration_minutes' => $service->duration_minutes,
                'status' => 'pending',
                'payment_status' => 'waiting'
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Consultation booked',
                'data' => $consultation
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Booking failed',
                'error' => $e->getMessage()
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
