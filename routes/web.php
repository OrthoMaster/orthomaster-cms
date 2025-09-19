<?php

use App\Http\Controllers\ContentController;
use App\Http\Controllers\HealthcareProfessionalController;
use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Route;
use TCG\Voyager\Facades\Voyager;
use Illuminate\Support\Facades\Storage;



Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();
});

Route::get('/', function () {
    return view('welcome');
    // return redirect('/admin');
});

Route::get('/test-s3', function () {
});


Route::group(['prefix' => 'admin', 'middleware' => 'admin.user'], function () {
    // Images
    Route::post('/upload-image-s3', [ImageController::class, 'uploadToS3'])->name('upload.image.s3');
    Route::delete('/delete-image-s3', [ImageController::class, 'deleteFromS3'])->name('delete.image.s3');

    // Formations
    Route::get('/formations/{formationId}/content', [ContentController::class, 'edit'])->name('content.edit');
    Route::post('/formations/{formationId}/update', [ContentController::class, 'update'])->name('content.update');
    Route::post('/formations/{formationId}/updateAsDraft', [ContentController::class, 'updateAsDraft'])->name('content.update.draft');



    // RPPS
    Route::get('/healthcare_professionals', [HealthcareProfessionalController::class, 'index'])->name('healthcare.index');
});
