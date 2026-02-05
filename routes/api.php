<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MentorController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\UserTypeController;
use App\Http\Controllers\Api\TopicCategoryController;
use App\Http\Controllers\Api\ServiceTypeController;
use App\Http\Controllers\Api\ScheduleController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::middleware(['auth:api'])->group(function () {

    Route::post('/mentors/regis', [MentorController::class, 'registerMentor']);
    Route::post('/mentors/online', [MentorController::class, 'updateOnlineStatus']);
    Route::post('/mentors/detail/{id}', [MentorController::class, 'detail']);
    Route::post('/mentors/list', [MentorController::class, 'listMentors']);

    Route::post('/mentors/schedule', [ScheduleController::class, 'createSchedule']);

    Route::post('/consultations/booking', [ConsultationController::class, 'booking']);

    Route::get('/user_types', [UserTypeController::class, 'userTypes']);
    Route::get('/topics/{id}', [TopicCategoryController::class, 'topics']);
    Route::get('/services', [ServiceTypeController::class, 'services']);
});
Route::middleware('auth:api')->get('/auth/test', function () {
    return response()->json(auth()->user());
});
