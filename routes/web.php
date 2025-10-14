<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\HealthcareProfessionalController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;
use TCG\Voyager\Facades\Voyager;
use Illuminate\Support\Facades\DB;


Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();
});

// Route::get('/db', function () {
//     try {
//         DB::connection()->getPdo();
//         return 'Connected!';
//     } catch (\Exception $e) {
//         return 'Error: ' . $e->getMessage();
//     }
// });

Route::get('/', function () {
    $domain = request()->getHost();

    if ($domain === 'cms.orthomaster.fr') {
        return redirect('/admin');
    }

    if ($domain === 'auth.orthomaster.fr') {
        return redirect('/register');
    }

    return view('welcome');
});

Route::get('/register', [AuthController::class, 'register'])->name('register.index');
Route::post('/register', [AuthController::class, 'submit'])->name('register.submit');
Route::post('/register/check-email', [AuthController::class, 'checkEmail'])->name('register.email.check');

Route::get('/forgot-password', [AuthController::class, 'viewForgotPage'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password', [AuthController::class, 'viewResetPage'])->name('password.reset');
Route::post('/password', [AuthController::class, 'reset'])->name('password.update');


Route::group(['prefix' => 'admin', 'middleware' => ['cmsAdmin']], function () {
    Route::get('/', [DashboardController::class, 'index'])->name('voyager.dashboard');


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

    // User accounts
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/account', [AccountController::class, 'create'])->name('account.create');
    Route::post('/account', [AccountController::class, 'store'])->name('account.store');
    Route::get('/account/{id}', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/{id}', [AccountController::class, 'update'])->name('account.update');

    Route::post('/account/{id}/confirm', [AccountController::class, 'confirm'])->name('account.confirm');
    Route::post('/account/{id}/resend', [AccountController::class, 'resend'])->name('account.resend');

    // Companies
    Route::get('/companies/{id}', [CompanyController::class, 'show'])->name('voyager.companies.show');
    Route::post('/companies/{id}/user/{userId}/update-manager', [CompanyController::class, 'updateManager'])->name('companies.manager.update');

});
