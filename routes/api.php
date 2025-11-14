<?php

use App\Http\Controllers\API\APILessonController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\CompanyController;
use App\Http\Controllers\API\TrainingController;
use App\Http\Controllers\Odoo\OdooAccountController;
use App\Http\Controllers\Tracking\TrkAppOpenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


/** Odoo - STA */
/** Odoo - END */

// Lesson
Route::get('/lessons/findContentId/{contentId}', [APILessonController::class, 'findByContentId']);
Route::get('/lessons/{id}/content', [APILessonController::class, 'getContent']);

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/login',    [AuthController::class, 'login']);

    Route::post('/sync',    [AuthController::class, 'sync']);
    
    Route::post('/logout',   [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me',        [AuthController::class, 'me'])->middleware('auth:sanctum');
});

// Tracking
Route::prefix('tracking')->group(function () {
    Route::post('/app/open',    [TrkAppOpenController::class, 'store']);
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
