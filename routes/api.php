<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MentorController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\UserTypeController;
use App\Http\Controllers\Api\TopicCategoryController;
use App\Http\Controllers\Api\ServiceTypeController;
// use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\PaymentController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::middleware(['auth:api'])->group(function () {

    Route::post('/mentors/regis', [MentorController::class, 'registerMentor']);
    Route::post('/mentors/online', [MentorController::class, 'updateOnlineStatus']);
    Route::post('/mentors/detail/{id}', [MentorController::class, 'detail']);
    Route::post('/mentors/list', [MentorController::class, 'listMentors']);
    Route::post('/mentors/consul', [MentorController::class, 'listConsultations']);
    Route::post('/mentors/consul/detail/{id}', [MentorController::class, 'detailConsultation']);
    Route::post('/mentors/consul/start/{id}', [MentorController::class, 'start']);
    Route::post('/mentors/consul/end/{id}', [MentorController::class, 'end']);


    Route::post('/consultations/booking', [ConsultationController::class, 'booking']);

     // create invoice xendit
    Route::post('/payment/xendit/{consultationId}', [PaymentController::class,'paymentXendit']);
    Route::post('/xendit/handle', [PaymentController::class,'handle']);

    // create manual payment
    Route::post('/payment/manual/{consultationId}', [PaymentController::class,'paymentManual']);
    Route::post('/payment/verify/{paymentId}', [PaymentController::class,'verifyManual']);

     // cek status order 
     Route::get('/payment/status/{orderNumber}', [PaymentController::class,'checkStatus']);
     // join room setelah paid
    Route::post('/payment/join-room/{orderNumber}', [PaymentController::class,'joinRoom']);

    // Get data
    Route::get('/user_types', [UserTypeController::class, 'userTypes']);
    Route::get('/topics/{id}', [TopicCategoryController::class, 'topics']);
    Route::get('/services', [ServiceTypeController::class, 'services']);

    // Route::post('/mentors/schedule', [ScheduleController::class, 'createSchedule']);
    Route::post('/consultations/pay', [ConsultationController::class, 'testPayment']);
});

// Route::post('/xendit/callback', [PaymentController::class,'callback']);

Route::middleware('auth:api')->get('/auth/test', function () {
    return response()->json(auth()->user());
});
