<?php

use Illuminate\Http\Request;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\LifelineController;
use App\Http\Controllers\LifelineUsageController;
use App\Http\Controllers\PlayQuizController;
use App\Http\Controllers\TransactionController;
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


Route::middleware('auth:sanctum')->prefix('quiz')->group(function(){
    Route::get('/', [QuizController::class, 'index']);
    Route::middleware('isAdmin')->post('/create', [QuizController::class, 'store']);
    Route::post('/submit', [QuizController::class, 'userResponse']);
    Route::get('/show', [QuizController::class, 'quizByNodeId']);
    Route::get('/contest', [QuizController::class, 'listVariant']);
    Route::middleware('isAdmin')->post('/variant/create', [QuizController::class, 'createVariant']);
    Route::get('/responses/list', [QuizController::class, 'responseList']);
    Route::post('/leaderboard', [QuizController::class, 'leaderboard']);
});


Route::prefix('category')->group(function(){
    Route::get('/', [CategoryController::class, 'index']);
    Route::post('/create', [CategoryController::class, 'store']);
});

Route::middleware('auth:sanctum')->prefix('lifeline')->group(function(){
    Route::get('/', [LifelineController::class, 'lifelines']);
    Route::post('/purchase', [LifelineController::class, 'purchaseLifeline']);
    Route::post('/use', [LifelineUsageController::class, 'useLifeline']);
});

Route::prefix('users')->group(function(){
    Route::middleware('auth:sanctum')->get('/user', [UserController::class, 'fetchUser']);
    Route::post('/create', [UserController::class, 'register']);
    Route::post('/login', [UserController::class, 'login']);
    Route::middleware('auth:sanctum')->post('/logout', [UserController::class, 'logout']);
    Route::middleware('auth:sanctum')->post('/update/upi', [UserController::class, 'updatePaymentUpi']);
});

Route::middleware('auth:sanctum')->prefix('funds')->group(function(){
    Route::post('/transaction', [TransactionController::class, 'make_transaction']);
    Route::post('/transaction/approval', [TransactionController::class, 'fundApproval']);
    Route::get('/transaction/list', [TransactionController::class, 'listTransactions']);
});

Route::middleware('auth:sanctum')->prefix('play')->group(function(){
    Route::post('/quiz/join', [PlayQuizController::class, 'join_quiz']);
    Route::post('/', [PlayQuizController::class, 'play_quiz']);
    Route::post('/next', [PlayQuizController::class, 'nextQuestion']);
});