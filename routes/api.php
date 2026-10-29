<?php

use App\Http\Controllers\API\AiController;
use App\Http\Controllers\API\ApplicationTrackingController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\ChatbotController;
use App\Http\Controllers\API\CompanyController;
use App\Http\Controllers\API\ContractController;
use App\Http\Controllers\API\DailyActionController;
use App\Http\Controllers\API\DocumentController;
use App\Http\Controllers\API\FavouriteController;
use App\Http\Controllers\API\MediaController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\PlanController;
use App\Http\Controllers\API\PreRegDiplomaRequestController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\PromotionController;
use App\Http\Controllers\API\SearchController;
use App\Http\Controllers\API\StripeWebhookController;
use App\Http\Controllers\API\ToolController;
use App\Http\Controllers\API\TrainingController;
use App\Http\Controllers\API\VideoAdController;
use App\Http\Controllers\GeneratePdfController;
use App\Http\Controllers\IntEmpTrackerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SystemNotificationController;
use App\Http\Controllers\Tracking\TrkAppOpenController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\API\BookingExpertController;
use App\Http\Controllers\API\ExpertBookingSetupController;
use App\Http\Controllers\API\AiVideoSearchController;
use App\Http\Controllers\API\BookingRequestController;
use App\Http\Controllers\API\ExpertAgendaController;
use App\Http\Controllers\API\BookingChatController;
use App\Http\Controllers\API\ExpertBookingRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Stripe webhook
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook']);

// Plans
Route::get('/plans', [PlanController::class, 'getPlans']);

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/sync', [AuthController::class, 'sync']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::get('/more', [AuthController::class, 'more'])->middleware('auth:sanctum');

    // Device
    Route::post('/device', [AuthController::class, 'storeDevice'])->middleware('auth:sanctum');

    // Ads
    Route::post('/ads/interest', [AuthController::class, 'sendAdsInterest'])->middleware('auth:sanctum');
});

// Tracking - Dont use anymore - TODO: delete
Route::prefix('tracking')->group(function () {
    Route::post('/app/open', [TrkAppOpenController::class, 'store']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    // Device token
    Route::post('/device-token', [AuthController::class, 'syncDeviceToken']);

    // Daily question
    Route::get('/daily-action', [DailyActionController::class, 'getTodayActionByUser']);
    Route::get('/daily-question/{id}', [DailyActionController::class, 'getQuestion']);
    Route::get('/podcast/{id}', [DailyActionController::class, 'getPodcast']);
    Route::get('/infographic/{id}', [DailyActionController::class, 'getInfographic']);
    Route::put('/daily-question', [DailyActionController::class, 'answerQuestion']);
    Route::put('/daily-podcast/count', [DailyActionController::class, 'countTimesPodcast']);

    // Profile
    Route::get('/profile/pre-data', [AuthController::class, 'getPreFromData']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/password', [AuthController::class, 'changePassword']);
    Route::put('/profile/settings', [AuthController::class, 'updateSettings']);
    Route::put('/profile/notifications', [AuthController::class, 'updateNotiPermission']);

    Route::put('/profile/topics/add', [AuthController::class, 'addTopic']);
    // Favourite
    Route::post('/favourites/topics/toggle', [FavouriteController::class, 'toggleTopic'])->middleware('throttle:30,1');
    Route::post('/favourites/toggle', [FavouriteController::class, 'toggleFavourite'])->middleware('throttle:30,1');
    Route::get('/favourites', [FavouriteController::class, 'getFavourites']);


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
    Route::patch('/company/remove', [CompanyController::class, 'removeCompany']);

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

        Route::get('/invoices/fifpl/{invoiceId}', [PaymentController::class, 'getFifplInvoice']);
        Route::post('/invoices/fifpl/create-intent', [PaymentController::class, 'createFifplPaymentIntent']);

        // Booking
        Route::get('/bookings/checkout', [PaymentController::class, 'bookingCheckout']);
        Route::post('/bookings/create-intent', [PaymentController::class, 'createBookingPaymentIntent']);
        Route::put('/bookings/{booking}/confirm', [PaymentController::class, 'confirmBookingPayment']);
        Route::put('/bookings/{booking}/cancel', [PaymentController::class, 'cancelBookingPayment']);
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

        Route::get('/ai-search/status', [AiVideoSearchController::class, 'status']);
        Route::post('/ai-search', [AiVideoSearchController::class, 'search']);
    });

    // Media
    Route::prefix('media')->group(function () {
        // Related
        Route::get('/{id}/related', [MediaController::class, 'getRelateds']);

        // Tracking
        Route::post('/{id}/track', [MediaController::class, 'trackMedia']);
        Route::post('/track/{trackId}/progress', [MediaController::class, 'trackProgress']);

        // Data
        Route::get('/events', [MediaController::class, 'getEvents']);
        Route::get('/newests', [MediaController::class, 'getNewests']);
        Route::get('/trends', [MediaController::class, 'getTrends']);
        Route::get('/categories', [MediaController::class, 'getCategories']);
        Route::get('/events/{id}/content', [MediaController::class, 'getEventMedias']);

        Route::get('/content', [MediaController::class, 'getContent']);
        Route::get('/tool/content', [MediaController::class, 'getToolContent']);
    });

    // Subscription
    Route::get('/subscription', [PlanController::class, 'currentSubscription']);
    Route::get('/subscription/usage', [PlanController::class, 'currentUsage']);
    Route::get('/subscription/check-feature/{featureKey}', [PlanController::class, 'checkFeature']);

    // Tracking
    Route::prefix('tracking')->group(function () {
        Route::post('/error', [ApplicationTrackingController::class, 'errorTracking']);
    });

    // Tool
    Route::get('/tools/user/brands', [ToolController::class, 'getSelectedProductBrands']);
    Route::post('/tools/user/brands', [ToolController::class, 'updateSelectedProductBrands']);

    // Pre-Reg
    Route::post('/pre-reg/diploma', [PreRegDiplomaRequestController::class, 'preReg']);

    // Product
    Route::get('/protocols/products', [ProductController::class, 'getProtocols']);
    Route::get('/rules/products', [ProductController::class, 'getRules']);
    Route::get('/rules/pathology-summary/{id}', [ProductController::class, 'getPathologySummary']);
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/count', [ProductController::class, 'count']);
        Route::get('/counts-by-group', [ProductController::class, 'countsByGroup']);
        Route::get('/{product}', [ProductController::class, 'show']);
    });

    // Video ads
    Route::prefix('video-ads')->group(function () {
        Route::get('feed', [VideoAdController::class, 'feed']);
        Route::post('{id}/impression', [VideoAdController::class, 'impression']);
        Route::post('{id}/view', [VideoAdController::class, 'view']);
        Route::post('{id}/contact', [VideoAdController::class, 'contact']);
    });

    // Chatbot
    Route::prefix('chatbot')->group(function () {
        Route::post('/ask-notebook', [AiController::class, 'ask']);

        Route::post('/ask', [ChatbotController::class, 'ask']);
        Route::get('/history/{conversationId}', [ChatbotController::class, 'history']);
        Route::get('/conversations', [ChatbotController::class, 'conversations']);
    });

    /* ===================== RENDEZ-VOUS EXPERTS ===================== */
    Route::prefix('booking')->group(function () {

        /* --- Utilisateur : parcourir les experts --- */
        Route::get('experts', [BookingExpertController::class, 'index']);
        Route::get('experts/{expertId}', [BookingExpertController::class, 'show'])->whereNumber('expertId');
        Route::get('experts/{expertId}/availability', [BookingExpertController::class, 'availability'])->whereNumber('expertId');

        /* --- Expert : sa propre configuration --- */
        Route::controller(ExpertBookingSetupController::class)->prefix('me')->group(function () {
            Route::get('/', 'show');
            Route::get('preview', 'preview');
            Route::put('settings', 'updateSettings');

            Route::post('offers', 'storeOffer');
            Route::put('offers/{offer}', 'updateOffer');
            Route::delete('offers/{offer}', 'destroyOffer');

            Route::post('rules', 'storeRules');
            Route::delete('rules/{rule}', 'destroyRule');
            Route::put('rules/{rule}/location', 'updateRuleLocation');

            Route::post('exceptions', 'storeExceptions');
            Route::delete('exceptions/{exception}', 'destroyException');

            Route::get('grid-dates', 'gridDates');
 
            Route::post('locations', 'storeLocation');
            Route::put('locations/{location}', 'updateLocation');
            Route::post('locations/{location}/archive', 'archiveLocation');
            Route::post('locations/{location}/restore', 'restoreLocation');
            Route::middleware('throttle:120,1')->group(function () {
                Route::get('places/autocomplete', 'placesAutocomplete');
                Route::get('places/{placeId}', 'placeDetails')->where('placeId', '[A-Za-z0-9_-]+');
            });
        });

        /* --- Booking - Clients --- */
        Route::get('/requests', [BookingRequestController::class, 'index']);
        Route::get('/requests/history', [BookingRequestController::class, 'history']);
        Route::get('/requests/{booking}', [BookingRequestController::class, 'show'])->whereNumber('booking');
        Route::post('/requests/{booking}/cancel', [BookingRequestController::class, 'cancel'])->whereNumber('booking');
        Route::post('/requests/{booking}/no-show', [BookingRequestController::class, 'reportNoShow'])->whereNumber('booking');

        Route::get('/domains', [BookingExpertController::class, 'domains']);

        /** --- Booking - Experts --- */
        Route::prefix('me')->group(function () {
            Route::get('/requests', [ExpertBookingRequestController::class, 'index']);
            Route::get('/requests/history', [ExpertBookingRequestController::class, 'history']);
            Route::get('/requests/{booking}', [ExpertBookingRequestController::class, 'show'])->whereNumber('booking');
            Route::post('/requests/{booking}/accept', [ExpertBookingRequestController::class, 'accept'])->whereNumber('booking');
            Route::post('/requests/{booking}/decline', [ExpertBookingRequestController::class, 'decline'])->whereNumber('booking');
            Route::get('/decline-reasons', [ExpertBookingRequestController::class, 'reasons']);

            Route::get('/agenda', [ExpertAgendaController::class, 'index']);
            Route::get('/agenda/history', [ExpertAgendaController::class, 'history']);
            Route::post('/requests/{booking}/no-show', [ExpertBookingRequestController::class, 'reportNoShow'])->whereNumber('booking');

            Route::put('/profile', [ExpertBookingSetupController::class, 'updateProfile']);
            Route::post('/domains', [ExpertBookingSetupController::class, 'storeDomain']);
        });

        /* --- Messagerie expert ↔ demandeur (les deux rôles) --- */
        Route::controller(BookingChatController::class)->prefix('conversations')->group(function () {
            Route::get('/', 'index');
            Route::get('unread', 'unread');
            Route::post('start', 'start')->middleware('throttle:30,1');
            Route::get('by-booking/{booking}', 'byBooking')->whereNumber('booking');
            Route::get('{conversation}', 'show')->whereNumber('conversation');
            Route::get('{conversation}/messages', 'messages')->whereNumber('conversation');
            Route::post('{conversation}/messages', 'send')->whereNumber('conversation')->middleware('throttle:30,1');
        });
    });
});

Route::group(['prefix' => 'admin', 'middleware' => ['cmsAdmin']], function () {
    // Test send notification
    Route::get('/notifications/test', [NotificationController::class, 'sendTest']);
    Route::get('/notifications/receipt', [NotificationController::class, 'checkReceipt']);
});

// Videos
Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    // Web endpoint (signed cookies)
    Route::post('/video/{videoCode}/signed-cookies', [VideoController::class, 'getSignedCookies']);

    // Mobile endpoint (proxy URL)
    Route::post('/video/{videoCode}/proxy-url', [VideoController::class, 'getProxyUrl']);
});
Route::get('/video/{videoCode}/mobile/master.m3u8', [VideoController::class, 'getMobileManifest'])
    ->name('video.mobile-manifest');

Route::post('/internal/calls', [IntEmpTrackerController::class, 'receiveData']);
Route::post('/internal/heartbeat', [IntEmpTrackerController::class, 'receiveHeartbeat']);
