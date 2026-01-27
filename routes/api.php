<?php

use App\Http\Controllers\API\APILessonController;
use App\Http\Controllers\API\ApplicationTrackingController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\ChatbotSearchController;
use App\Http\Controllers\API\CompanyController;
use App\Http\Controllers\API\ContractController;
use App\Http\Controllers\API\DailyActionController;
use App\Http\Controllers\API\DocumentController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\PromotionController;
use App\Http\Controllers\API\QuotationController;
use App\Http\Controllers\API\SearchController;
use App\Http\Controllers\API\StripeWebhookController;
use App\Http\Controllers\API\TrainingController;
use App\Http\Controllers\GeneratePdfController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SystemNotificationController;
use App\Http\Controllers\Tracking\TrkAppOpenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route::get('/quotation', [QuotationController::class, 'getContent']);
// Route::get('/quotation/sample', [QuotationController::class, 'generateSample']);
// Route::get('/quotation/sample/signed', [QuotationController::class, 'addSignature']);

// Lesson
// Route::get('/lessons/findContentId/{contentId}', [APILessonController::class, 'findByContentId']);
// Route::get('/lessons/{id}/content', [APILessonController::class, 'getContent']);

// Chatbot search temp
// Route::get('/search/trainings', [ChatbotSearchController::class, 'searchTrainings']);
// Route::get('/search/videos', [ChatbotSearchController::class, 'searchVideos']);

// Stripe webhook
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook']);

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/sync', [AuthController::class, 'sync']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::get('/more', [AuthController::class, 'more'])->middleware('auth:sanctum');

    // Device
    Route::post('/device', [AuthController::class, 'storeDevice'])->middleware('auth:sanctum');
});

// Tracking
Route::prefix('tracking')->group(function () {
    Route::post('/app/open', [TrkAppOpenController::class, 'store']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    // Daily question
    Route::get('/daily-action', [DailyActionController::class, 'getTodayActionByUser']);
    Route::put('/daily-question', [DailyActionController::class, 'answerQuestion']);
    Route::put('/daily-podcast/count', [DailyActionController::class, 'countTimesPodcast']);

    // Profile
    Route::get('/profile/pre-data', [AuthController::class, 'getPreFromData']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/password', [AuthController::class, 'changePassword']);
    Route::put('/profile/settings', [AuthController::class, 'updateSettings']);
    Route::put('/profile/notifications', [AuthController::class, 'updateNotiPermission']);

    Route::put('/profile/topics/add', [AuthController::class, 'addTopic']);

    // Notification
    Route::get('/notifications', [SystemNotificationController::class, 'getNotifications']);
    Route::get('/notifications/lastest', [SystemNotificationController::class, 'getLastest']);
    Route::post('/notifications/read', [SystemNotificationController::class, 'markAllAsRead']);
    Route::put('/notifications/{id}/read', [SystemNotificationController::class, 'markAsRead']);
    Route::put('/notifications/{id}/accept', [SystemNotificationController::class, 'accept']);
    Route::put('/notifications/{id}/reject', [SystemNotificationController::class, 'reject']);

    Route::delete('/notifications', [SystemNotificationController::class, 'deleteAll']);
    Route::delete('/notifications/{id}', [SystemNotificationController::class, 'delete']);

    Route::get('/notifications/unread-count', [SystemNotificationController::class, 'getUnreadCount']);

    // Trainings
    Route::get('/trainings', [TrainingController::class, 'getAll']);
    Route::get('/trainings/packages/{packageId}', [TrainingController::class, 'getPackage']);
    Route::post('/trainings/lesson/content', [TrainingController::class, 'getLessonContent']);
    Route::put('/trainings/lesson/complete', [TrainingController::class, 'completeLesson']);

    // Company
    Route::get('/members', [CompanyController::class, 'getMembers']);

    Route::get('/company', [CompanyController::class, 'getCompany']);
    Route::post('/company', [CompanyController::class, 'createCompany']);
    Route::get('/company/pre-data', [CompanyController::class, 'getPreFromData']);
    Route::put('/company/{id}', [CompanyController::class, 'updateCompany']);
    Route::patch('/company/update-manager', [CompanyController::class, 'updateManager']);
    Route::patch('/company/remove-member', [CompanyController::class, 'removeMember']);
    Route::patch('/company/invite-member', [CompanyController::class, 'inviteMember']);
    Route::patch('/company/quit', [CompanyController::class, 'quitCompany']);

    // Cart
    Route::get('/cart', [CartController::class, 'getDetails']);
    Route::put('/cart/add/{id}', [CartController::class, 'addPackage']);
    Route::delete('/cart/remove/{id}', [CartController::class, 'removePackage']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);

    // Pro-cart
    Route::get('/pro-cart', [CartController::class, 'getDetailsProCart']);
    Route::get('/pro-cart/payment-methods', [CartController::class, 'getPaymentMethods']);
    Route::put('/pro-cart/members', [CartController::class, 'syncMembers']);
    Route::get('/pro-cart/trainings', [CartController::class, 'getTrainingsWithPackages']);
    Route::put('/pro-cart/trainings', [CartController::class, 'addPackagesToProCart']);
    Route::get('/pro-cart/preview', [CartController::class, 'previewProCart']);

    Route::post('/pro-cart/contract/generate', [ContractController::class, 'generate']);
    Route::post('/pro-cart/contract/sign', [ContractController::class, 'sign']);

    // Payment
    Route::prefix('payments')->group(function () {
        Route::post('/invoices/create-intent', [PaymentController::class, 'createPaymentIntent']);
        Route::put('/invoices/{invoice}/confirm', [PaymentController::class, 'confirmPayment']);
        Route::put('/invoices/{invoice}/cancel', [PaymentController::class, 'cancelPayment']);
    });

    // Documents
    Route::get('/documents', [DocumentController::class, 'getAll']);

    // Promotions
    Route::prefix('promotions')->group(function () {
        Route::post('/validate', [PromotionController::class, 'validate']);
    });

    // Invoice
    Route::prefix('generate')->group(function () {
        Route::get('/invoices/{id}', [GeneratePdfController::class, 'paymentInvoice']);
    });

    // Search content
    Route::prefix('content')->group(function () {
        Route::get('/pre-search', [SearchController::class, 'getPreSearch']);
        Route::post('/search', [SearchController::class, 'search']);
        Route::delete('/search/history/{id}', [SearchController::class, 'deleteKeyword']);
        Route::delete('/search/histories', [SearchController::class, 'clearHistory']);
        
        Route::post('/search/lesson-openable', [SearchController::class, 'checkOpenableLesson']);

        Route::post('/s3-url', [SearchController::class, 'getS3Url']);
    });

    
    // Tracking
    Route::prefix('tracking')->group(function () {
        Route::post('/error', [ApplicationTrackingController::class, 'errorTracking']);
        
    });
});

Route::group(['prefix' => 'admin', 'middleware' => ['cmsAdmin']], function () {
    // Test send notification
    Route::get('/notifications/test', [NotificationController::class, 'sendTest']);
    Route::get('/notifications/receipt', [NotificationController::class, 'checkReceipt']);
});