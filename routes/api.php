<?php

use App\Http\Controllers\API\APILessonController;
use App\Http\Controllers\FairPlay\FairPlayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// FairPlay certification
Route::get('/fairplay/certificate', [FairPlayController::class, 'getCertificate']);
Route::get('/fairplay/certificate/clear-cache', [FairPlayController::class, 'clearCache']);

// Lesson
Route::get('/lessons/findContentId/{contentId}', [APILessonController::class, 'findByContentId']);
Route::get('/lessons/{id}/content', [APILessonController::class, 'getContent']);
