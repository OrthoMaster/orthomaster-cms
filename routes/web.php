<?php

use App\Http\Controllers\ContentController;
use App\Http\Controllers\HealthcareProfessionalController;
use Illuminate\Support\Facades\Route;
use TCG\Voyager\Facades\Voyager;

Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();
});

Route::get('/', function () {
    return view('welcome');
    // return redirect('/admin');
});

Route::group(['prefix' => 'admin', 'middleware' => 'admin.user'], function () {
    Route::get('/formation/{id}/content', [ContentController::class, 'edit'])->name('content.edit');

    // voyager-search
    Route::get('/healthcare_professionals', [HealthcareProfessionalController::class, 'index'])->name('healthcare.index');
});


