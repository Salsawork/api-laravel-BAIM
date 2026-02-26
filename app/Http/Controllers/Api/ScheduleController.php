<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Mentor;

use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function createSchedule(Request $request)
    {
        $mentor = Mentor::where('user_id',auth()->id())->first();
    
        if(!$mentor){
            return response()->json([
                'success'=>false,
                'message'=>'Bukan mentor'
            ],403);
        }
    
        $request->validate([
            'day_of_week' => 'required|in:senin,selasa,rabu,kamis,jumat,sabtu,minggu',
            'date' => 'required',
            'start_time' => 'required',
            'end_time' => 'required',
            'price' => 'nullable|numeric|min:0'
        ]);
    
        if($request->end_time <= $request->start_time){
            return response()->json([
                'success'=>false,
                'message'=>'End time harus lebih besar dari start'
            ],422);
        }

        if($mentor->user_type_id == 1){
    
            if(!$request->filled('price')){
                return response()->json([
                    'success'=>false,
                    'message'=>'Muthowif wajib isi harga'
                ],422);
            }
    
        }else{
            // selain id 1  null
            $request->merge([
                'price'=>null
            ]);
        }
    
        $schedule = Schedule::create([
            'mentor_id'=>$mentor->id,
            'day_of_week'=>$request->day_of_week,
            'start_time'=>$request->start_time,
            'end_time'=>$request->end_time,
            'date'=>$request->date,
            'price'=>$request->price,
            'is_active'=>1
        ]);
    
        return response()->json([
            'success'=>true,
            'message'=>'Schedule berhasil dibuat',
            'data'=>$schedule
        ]);
    }

    public function updateSchedule(Request $request, $id)
    {
        $mentor = Mentor::where('user_id',auth()->id())->first();

        if(!$mentor){
            return response()->json([
                'success'=>false,
                'message'=>'Bukan mentor'
            ],403);
        }

        $schedule = Schedule::where('id',$id)
            ->where('mentor_id',$mentor->id)
            ->first();

        if(!$schedule){
            return response()->json([
                'success'=>false,
                'message'=>'Schedule tidak ditemukan'
            ],404);
        }

        $request->validate([
            'day_of_week' => 'required|in:senin,selasa,rabu,kamis,jumat,sabtu,minggu',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'date' => 'nullable',
            'price' => 'nullable|numeric|min:0'
        ]);

        if($request->end_time <= $request->start_time){
            return response()->json([
                'success'=>false,
                'message'=>'End time harus lebih besar dari start'
            ],422);
        }

        // RULE PRICE
        if($mentor->user_type_id == 1){

            if(!$request->filled('price')){
                return response()->json([
                    'success'=>false,
                    'message'=>'Muthowif wajib isi harga'
                ],422);
            }

        }else{
            $request->merge([
                'price'=>null
            ]);
        }

        $schedule->update([
            'day_of_week'=>$request->day_of_week,
            'start_time'=>$request->start_time,
            'end_time'=>$request->end_time,
            'date'=>$request->date,
            'price'=>$request->price
        ]);

        return response()->json([
            'success'=>true,
            'message'=>'Schedule updated',
            'data'=>$schedule
        ]);
    }

    //delete schedule
    public function deleteSchedule($id)
    {
        $mentor = Mentor::where('user_id',auth()->id())->first();

        if(!$mentor){
            return response()->json([
                'success'=>false,
                'message'=>'Bukan mentor'
            ],403);

        }

        $schedule = Schedule::where('id',$id)
            ->where('mentor_id',$mentor->id)
            ->first();

        if(!$schedule){
            return response()->json([
                'success'=>false,
                'message'=>'Schedule tidak ditemukan'
            ],404);
        }

        $schedule->delete();

        return response()->json([
            'success'=>true,
            'message'=>'Schedule deleted'
        ]);
    }
    // untuk mentor
    public function mySchedules()
    {
        $mentor = Mentor::where('user_id',auth()->id())->first();

        $data = Schedule::where('mentor_id',$mentor->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success'=>true,
            'total'=>$data->count(),
            'data'=>$data
        ]);
    }

    //untuk user lihat jadwal
    public function getMentorSchedules($mentorId)
    {
        $mentor = Mentor::where('id',$mentorId)
            ->where('is_verified',1)
            ->first();

        if(!$mentor){
            return response()->json([
                'success'=>false,
                'message'=>'Mentor tidak ditemukan'
            ],404);
        }

        $schedules = Schedule::where('mentor_id',$mentor->id)
            ->where('is_active',1)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success'=>true,
            'mentor'=>$mentor->full_name,
            'data'=>$schedules
        ]);
    }
}
