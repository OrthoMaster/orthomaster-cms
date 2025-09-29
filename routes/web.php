<?php

use App\Http\Controllers\ContentController;
use App\Http\Controllers\HealthcareProfessionalController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;
use TCG\Voyager\Facades\Voyager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;


Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();
});

Route::get('/db', function () {
    try {
        DB::connection()->getPdo();
        return 'Connected!';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

Route::get('/', function () {
    return view('welcome');
    // return redirect('/admin');
});

Route::group(['prefix' => 'admin', 'middleware' => 'admin.user'], function () {
    // Images
    Route::post('/upload-image-s3', [ImageController::class, 'uploadToS3'])->name('upload.image.s3');
    Route::delete('/delete-image-s3', [ImageController::class, 'deleteFromS3'])->name('delete.image.s3');

    // Videos
    Route::get('/upload-video-s3', [VideoController::class, 'edit'])->name('edit.video.s3');
    Route::post('/upload-video-s3', [VideoController::class, 'uploadToS3'])->name('upload.video.s3');
    Route::delete('/delete-video-s3', [VideoController::class, 'delete'])->name('delete.video.s3');

    // Lessons
    Route::get('/trainings/{trainingId}/lesson/create', [LessonController::class, 'create'])->name('lessons.create');

    Route::get('/trainings/{trainingId}/lessons', [LessonController::class, 'index'])->name('trainings.lessons.index');
    Route::get('/trainings/{trainingId}/lessons/order', [LessonController::class, 'orderView'])->name('trainings.lessons.order');
    Route::post('/trainings/{trainingId}/lessons/order', [LessonController::class, 'updateOrder'])->name('trainings.lessons.order.update');

    

    Route::get('/lessons/{id}/content', [ContentController::class, 'edit'])->name('content.edit');
    Route::post('/lessons/{id}/content', [ContentController::class, 'update'])->name('content.update');
    Route::post('/lessons/{id}/content/updateAsDraft', [ContentController::class, 'updateAsDraft'])->name('content.update.draft');

    

    // RPPS
    Route::get('/healthcare_professionals', [HealthcareProfessionalController::class, 'index'])->name('healthcare.index');
});
