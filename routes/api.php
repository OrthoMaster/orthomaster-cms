<?php

use App\Http\Controllers\API\APILessonController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\FairPlay\FairPlayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/** FairPlay - STA */
// FairPlay Certificate endpoint
Route::options('/fairplay/certificate', [FairPlayController::class, 'certificateOptions']);
Route::get('/fairplay/certificate', [FairPlayController::class, 'getCertificate']);

// FairPlay License endpoint  
Route::options('/fairplay/license', [FairPlayController::class, 'licenseOptions']);
Route::post('/fairplay/license', [FairPlayController::class, 'getLicense']);

// Test endpoint (optional, only for development)
Route::get('/fairplay/test', [FairPlayController::class, 'testConfiguration']);

/** FairPlay - END */


// Lesson
Route::get('/lessons/findContentId/{contentId}', [APILessonController::class, 'findByContentId']);
Route::get('/lessons/{id}/content', [APILessonController::class, 'getContent']);

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:6,1');
    
    Route::post('/logout',   [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me',        [AuthController::class, 'me'])->middleware('auth:sanctum');
});
