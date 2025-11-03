<?php

use App\Http\Controllers\API\APILessonController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\CompanyController;
use App\Http\Controllers\API\TrainingController;
use App\Http\Controllers\FairPlay\FairPlayController;
use App\Http\Controllers\Odoo\OdooAccountController;
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


/** Odoo - STA */
Route::get('/test', [OdooAccountController::class, 'test']);

/** Odoo - END */

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

Route::middleware(['auth:sanctum'])->group(function () {
    // Trainings
    Route::get('/trainings', [TrainingController::class, 'getAll']);

    // Members
    Route::get('/members', [CompanyController::class, 'getMembers']);

    // Cart
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/add/{id}', [CartController::class, 'addPackage']);
    Route::delete('/cart/remove/{id}', [CartController::class, 'removePackage']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);
    Route::post('/cart/members', [CartController::class, 'syncMembers']);
    Route::post('/cart', [CartController::class, 'submit']);
});
