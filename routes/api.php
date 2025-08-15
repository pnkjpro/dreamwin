<?php

use Illuminate\Http\Request;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\LifelineController;
use App\Http\Controllers\LifelineUsageController;
use App\Http\Controllers\PlayQuizController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ExpertVideoController;
use App\Http\Controllers\QueryController;
use App\Http\Controllers\VariableController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\RazorpayController;
use App\Http\Controllers\Auth\UserController;
use Illuminate\Support\Facades\Route;
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Route::post('/register', [UserAuthController::class, 'register']);
// Route::post('/login', [UserAuthController::class, 'login']);
// Route::post('/verify-mobile', [UserAuthController::class, 'verifyMobile']);

// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/logout', [UserAuthController::class, 'logout']);
// });


Route::prefix('quiz')->group(function(){
    Route::get('/', [QuizController::class, 'index']);
    Route::middleware('auth:sanctum')->post('/submit', [QuizController::class, 'userResponse']);
    Route::get('/contest', [QuizController::class, 'listVariant']);
    Route::middleware('auth:sanctum')->get('/responses/list', [QuizController::class, 'responseList']);
    Route::middleware('auth:sanctum')->post('/leaderboard', [QuizController::class, 'leaderboard']);
    Route::middleware('auth:sanctum')->post('/user/answerKey', [QuizController::class, 'showAnswerKey']);
});

Route::middleware(['auth:sanctum','isAdmin'])->prefix('admin')->group(function(){
    Route::post('/quiz/create', [QuizController::class, 'store']);
    Route::post('/variant/create', [QuizController::class, 'createVariant']);
    Route::post('/transaction/approval', [TransactionController::class, 'fundApproval']);
    Route::get('/transaction/list/all', [TransactionController::class, 'listAllTransactions']);
    Route::post('/banner/update', [HomeController::class, 'updateBanner']);
    Route::post('/lifeline/update', [LifelineController::class, 'updateLifeline']);
    Route::post('/category/create', [CategoryController::class, 'store']);
    Route::post('/categories/update-order', [CategoryController::class, 'updateSorting']);
    Route::post('/howVideos/update', [HomeController::class, 'updateHowVideos']);
    Route::get('/list/leaderboards', [QuizController::class, 'listAdminLeaderboard']);
    Route::post('/show/leaderboard', [QuizController::class, 'showAdminLeaderboard']);
    Route::get('/user/list', [UserController::class, 'userList']);
    Route::get('/quiz/list', [QuizController::class, 'quizList']);
    Route::get('/show/quiz/users', [QuizController::class, 'quizByNodeId']);

    Route::get('variables', [VariableController::class, 'index']);
    Route::post('variables', [VariableController::class, 'store']);
    Route::get('variables/{name}', [VariableController::class, 'show']);
    Route::post('variables/update', [VariableController::class, 'update']);
    Route::delete('variables/{name}', [VariableController::class, 'destroy']);

});
Route::middleware('auth:sanctum')->prefix('featured')->group(function(){
    Route::get('/videos', [HomeController::class, 'listFeaturedVideos']);
    Route::middleware('isAdmin')->post('/video/update', [HomeController::class, 'updateFeaturedVideo']);
    Route::middleware('isAdmin')->post('/video/create', [HomeController::class, 'createFeaturedVideo']);
    Route::middleware('isAdmin')->post('/video/delete', [HomeController::class, 'deleteFeaturedVideo']);
});

Route::middleware('auth:sanctum')->prefix('expert')->group(function(){
    Route::middleware('isAdmin')->get('/videos', [ExpertVideoController::class, 'listExpertVideos']);
    Route::middleware('isAdmin')->post('/video/update', [ExpertVideoController::class, 'updateExpertVideo']);
    Route::middleware('isAdmin')->post('/video/create', [ExpertVideoController::class, 'createExpertVideo']);
    Route::middleware('isAdmin')->post('/video/delete', [ExpertVideoController::class, 'deleteExpertVideo']);
    Route::post('/video/purchase', [ExpertVideoController::class, 'purchaseExpertVideo']);
    Route::get('/video/list', [ExpertVideoController::class, 'listUserExpertVideos']);
});

Route::prefix('bot')->group(function(){
    Route::post('/create', [BotController::class, 'createBot']);
    Route::post('/action/create', [BotController::class, 'createActions']);
    Route::get('/list', [BotController::class, 'getBots']);
    Route::get('/quizzes', [BotController::class, 'getQuizzes']);
});

Route::prefix('category')->group(function(){
    Route::get('/', [CategoryController::class, 'index']);  
    Route::get('/quiz/list', [CategoryController::class, 'quizzesByCategoryId']);
});

Route::middleware('auth:sanctum')->prefix('lifeline')->group(function(){
    Route::get('/', [LifelineController::class, 'lifelines']);
    Route::get('/transaction/list', [LifelineController::class, 'lifelineTransactions']);
    Route::get('/history/list', [LifelineController::class, 'lifelineUsageHistory']);
    Route::get('/list', [LifelineController::class, 'fetchLifeline']);
    Route::post('/purchase', [LifelineController::class, 'purchaseLifeline']);
    Route::post('/use', [LifelineUsageController::class, 'useLifeline']);
});

Route::prefix('users')->group(function(){
    Route::middleware('auth:sanctum')->get('/user', [UserController::class, 'fetchUser']);
    Route::post('/create', [UserController::class, 'register']);
    Route::post('/login', [UserController::class, 'login']);
    Route::middleware('auth:sanctum')->post('/logout', [UserController::class, 'logout']);
    Route::middleware('auth:sanctum')->post('/update/upi', [UserController::class, 'updatePaymentUpi']);
    Route::post('/otp/send', [OtpController::class, 'sendOtp']);
    Route::post('/otp/verify', [OtpController::class, 'verifyOtp']);
    Route::middleware('auth:sanctum')->post('/password/reset', [UserController::class, 'updatePassword']);
    Route::middleware('auth:sanctum')->get('/refer/list', [UserController::class, 'listReferredUsers']);
});

Route::middleware('auth:sanctum')->prefix('funds')->group(function(){
    Route::post('/transaction', [TransactionController::class, 'make_transaction']);
    Route::get('/transaction/list', [TransactionController::class, 'listTransactions']);
});

Route::middleware('auth:sanctum')->prefix('play')->group(function(){
    Route::post('/quiz/join', [PlayQuizController::class, 'join_quiz']);
    Route::post('/', [PlayQuizController::class, 'play_quiz']);
    Route::post('/next', [PlayQuizController::class, 'nextQuestion']);
});

Route::get('banner/list', [HomeController::class, 'listBanner']);
Route::get('how/videos', [HomeController::class, 'listHowVideos']);

Route::middleware('auth:sanctum')->prefix('razorpay')->group(function(){
    Route::post('/order/create', [RazorpayController::class, 'createOrder']);
    Route::post('/payment/verify', [RazorpayController::class, 'verifyPayment']);
});
Route::post('/razorpay/webhook', [RazorpayController::class, 'handleWebhook']);

Route::middleware(['auth:sanctum', 'isAdmin'])->prefix('run')->group(function(){
    Route::get('/net/funds', [QueryController::class, 'fundsNetDifference']);
    Route::get('/net/profit', [QueryController::class, 'netProfit']);
    Route::get('/net/withdrawal', [QueryController::class, 'netWithdrawal']);
});