<?php

use Illuminate\Http\Request;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Auth\UserAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/register', [UserAuthController::class, 'register']);
Route::post('/login', [UserAuthController::class, 'login']);
Route::post('/verify-mobile', [UserAuthController::class, 'verifyMobile']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UserAuthController::class, 'logout']);
});



Route::prefix('quiz')->group(function(){
    Route::get('/', [QuizController::class, 'index']);
    Route::get('/list', [CategoryController::class, 'listQuizzes']);
});


Route::prefix('category')->group(function(){
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/create', [CategoryController::class, 'store']);
    Route::get('');
});