<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\Mentor;

use Illuminate\Http\Request;

class ScheduleController extends Controller
{
   
    public function createSchedule(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time'
        ]);

        $mentor = Mentor::where('user_id', auth()->id())->first();

        if (!$mentor) {
            return response()->json([
                'status' => false,
                'message' => 'Mentor profile not found'
            ], 404);
        }

        Schedule::create([
            'mentor_id' => $mentor->id,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'is_booked' => 0
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Schedule created'
        ]);
    }
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Schedule $schedule)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Schedule $schedule)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Schedule $schedule)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Schedule $schedule)
    {
        //
    }
}
