<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\HealthcareProfessionalController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VideoController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use TCG\Voyager\Facades\Voyager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;


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



Route::get('/test-mail', function () {
    // $user = User::find(1);
    // return view('emails.admin.has-new-user', compact('user'));
    // return MailController::createdNewAccount($user);
});

Route::get('/register', [AuthController::class, 'register'])->name('register.index');
Route::post('/register', [AuthController::class, 'submit'])->name('register.submit');
Route::post('/register/check-email', [AuthController::class, 'checkEmail'])->name('register.email.check');

Route::get('/forgot-password', [AuthController::class, 'viewForgotPage'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password', [AuthController::class, 'viewResetPage'])->name('password.reset');
Route::post('/password', [AuthController::class, 'reset'])->name('password.update');


Route::group(['prefix' => 'admin', 'middleware' => ['cmsAdmin']], function () {
    // Images
    Route::post('/upload-image-s3/lesson', [ImageController::class, 'uploadLessonImageToS3'])->name('upload.image.s3.lesson');
    Route::post('/upload-image-s3/student-certificate', [ImageController::class, 'uploadStudentCertificateToS3'])->name('upload.image.s3.student');
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

    // Admin
    Route::get('/admins', [AdminController::class, 'index'])->name('cmsAdmin.index');
});
