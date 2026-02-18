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
use App\Http\Controllers\Api\BankController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::middleware(['auth:api'])->group(function () {

    Route::post('/mentors/regis', [MentorController::class, 'registerMentor']);
    Route::post('/mentors/detail/{id}', [MentorController::class, 'detail']);
    Route::post('/mentors/list', [MentorController::class, 'listMentors']);
    Route::post('/toggle/online', [MentorController::class, 'toggleOnline']);
    Route::post('/mentors/presence', [MentorController::class, 'mentorPresence']);
    
    Route::post('/mentors/consul', [MentorController::class, 'mentorConsultations']);
    Route::post('/mentors/consul/detail/{id}', [MentorController::class, 'detailConsultation']);

    Route::post('/consultations/booking', [ConsultationController::class, 'booking']);
    // join room setelah paid
    Route::post('/join-room/{orderNumber}', [ConsultationController::class,'joinRoom']);
    Route::post('/join-chat/{orderNumber}', [ConsultationController::class,'joinChat']);
    Route::post('/end-session/{orderNumber}', [ConsultationController::class,'endSession']);
    // customer
    Route::get('/consultations/history/customer',[ConsultationController::class,'historyCustomer']);
    // mentor
    Route::get('/consultations/history/mentor',[ConsultationController::class,'historyMentor']);
     // create invoice xendit
    Route::post('/payment/xendit/{consultationId}', [PaymentController::class,'paymentXendit']);
    Route::post('/xendit/handle', [PaymentController::class,'handle']);

    // create manual payment
    Route::post('/payment/manual/{consultationId}', [PaymentController::class,'paymentManual']);
    Route::post('/payment/verify/{paymentId}', [PaymentController::class,'verifyManual']);
     Route::get('/payment/status/{orderNumber}', [PaymentController::class,'checkStatus']);

   
    // Route::post('/mentors/schedule', [ScheduleController::class, 'createSchedule']);
    Route::post('/consultations/pay', [ConsultationController::class, 'testPayment']);
});
 // Get data
 Route::get('/user_types', [UserTypeController::class, 'userTypes']);
 Route::get('/banks', [BankController::class, 'banks']);
 Route::get('/topics/{id}', [TopicCategoryController::class, 'topics']);
 Route::get('/services', [ServiceTypeController::class, 'services']);


// Route::post('/xendit/callback', [PaymentController::class,'callback']);

Route::middleware('auth:api')->get('/auth/test', function () {
    return response()->json(auth()->user());
});
