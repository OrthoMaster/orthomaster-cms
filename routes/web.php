<?php

use Illuminate\Http\Request;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\API\AiController;
use App\Http\Controllers\AuthCompanyRequestController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\HealthcareProfessionalController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrochureController;
use App\Http\Controllers\BrochureDemoController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DailyQuestionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardDailyActionController;
use App\Http\Controllers\DashboardEventController;
use App\Http\Controllers\DashboardLessonController;
use App\Http\Controllers\DashboardReportController;
use App\Http\Controllers\DashboardUserController;
use App\Http\Controllers\DashboardVideoAdsController;
use App\Http\Controllers\DashboardVideoViewsController;
use App\Http\Controllers\GenerateDocumentController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\InfographicController;
use App\Http\Controllers\IntEmpTrackerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MktShortLinkController;
use App\Http\Controllers\MktToolsController;
use App\Http\Controllers\NotificationTestController;
use App\Http\Controllers\PodcastController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPreDataController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\SaleAssignmentController;
use App\Http\Controllers\TrainingPermissionController;
use App\Http\Controllers\WebController;
use Illuminate\Support\Facades\Route;
use TCG\Voyager\Facades\Voyager;

Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();
});

Route::get('/', function () {
    $domain = request()->getHost();

    if ($domain === 'cms.orthomaster.fr') {
        return redirect('/admin');
    }

    if ($domain === 'auth.orthomaster.fr') {
        return redirect('/register');
    }

    if ($domain === 'expert.orthomaster.fr') {
        return redirect('/demo');
    }

    return redirect()->away('https://orthomaster.fr', 301);
});

// Demo
Route::get('/demo', function () {
    return view('demo.orthomaster');
});
Route::get('/exp/{expert:slug}', [BrochureController::class, 'show'])
    ->where('expert', '[a-z0-9-]+');
Route::domain(config('brochures.domain'))->group(function () {
    // Éditeur (lien signé pour le médecin, ou admin connecté)
    Route::get('/demo/{expert:slug}', [BrochureDemoController::class, 'show'])
        ->name('brochures.demo');
    Route::post('/demo/{expert:slug}', [BrochureDemoController::class, 'save'])
        ->middleware('throttle:60,1')
        ->name('brochures.demo.save');
    Route::post('/demo/{expert:slug}/avatar', [BrochureDemoController::class, 'avatar'])
        ->middleware('throttle:10,1')
        ->name('brochures.demo.avatar');
    Route::get('/demo/{expert:slug}/export', [BrochureDemoController::class, 'export'])
        ->name('brochures.demo.export');

    Route::get('/{expert:slug}', [BrochureController::class, 'show'])
        ->where('expert', '[a-z0-9-]+')
        ->name('brochures.show');
});

Route::get('/login', function () {
    return redirect()->route('voyager.login');
})->name('login');

Route::get('/register', [AuthController::class, 'register'])->name('register.index');
Route::post('/register', [AuthController::class, 'submit'])->name('register.submit');
Route::post('/register/check-email', [AuthController::class, 'checkEmail'])->name('register.email.check');

Route::get('/forgot-password', [AuthController::class, 'viewForgotPage'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password', [AuthController::class, 'viewResetPage'])->name('password.reset');
Route::post('/password', [AuthController::class, 'reset'])->name('password.update');

Route::post('/group/create-by-user', [AuthCompanyRequestController::class, 'storeByUser'])->name('register.store.user');

Route::get('/group/register/{code}', [AuthCompanyRequestController::class, 'view'])->name('register.company.view');
Route::post('/group/register/{code}/draft', [AuthCompanyRequestController::class, 'submit'])->name('register.company.draft');

Route::get('/group/members', [AuthCompanyRequestController::class, 'redirectRegisterView'])->name('register.company.redirect');

Route::get('/legal-notice', [WebController::class, 'legalNotice'])->name('web.legal-notice');

Route::group(['prefix' => 'admin', 'middleware' => ['cmsAdmin']], function () {
    Route::get('/', [DashboardUserController::class, 'index'])->name('voyager.dashboard');

    // Accounts
    Route::prefix('dashboard/users')
        ->name('dashboard.users.')
        ->group(function () {
            Route::get('/', [DashboardUserController::class, 'index'])->name('index');
            Route::get('/data', [DashboardUserController::class, 'chartData'])->name('data');
        });

    // Lessons
    Route::prefix('dashboard/lessons')
        ->name('dashboard.lessons.')
        ->group(function () {
            Route::get('/', [DashboardLessonController::class, 'index'])->name('index');
            Route::get('/data', [DashboardLessonController::class, 'chartData'])->name('data');
            Route::get('/search-lessons', [DashboardLessonController::class, 'searchLessons'])->name('search-lessons');
        });

    // Videos
    Route::prefix('dashboard/medias')
        ->name('medias.')
        ->group(function () {
            Route::get('/', [MediaController::class, 'index'])->name('index');
            Route::get('/data', [MediaController::class, 'chartData'])->name('data');
            Route::get('/search-experts', [MediaController::class, 'searchExperts'])->name('search-experts');
            Route::get('/search-videos', [MediaController::class, 'searchVideos'])->name('search-videos');

            Route::get('/create', [MediaController::class, 'create'])->name('create');
            Route::post('/', [MediaController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [MediaController::class, 'edit'])->name('edit');
            Route::put('/{id}', [MediaController::class, 'update'])->name('update');
            Route::post('/{id}/create-version', [MediaController::class, 'createVersion'])->name('create-version');

            Route::post('/{id}/content/image', [MediaController::class, 'uploadContentImage'])->name('content.image');

            Route::get('/{id}/linked/{type}', [MediaController::class, 'linkedList'])->name('linked');
        });
    Route::get('medias/list', [MediaController::class, 'list'])->name('medias.list');

    // Events
    Route::get('dashboard/events', [DashboardEventController::class, 'index'])
        ->name('dashboard.events');
    Route::prefix('dashboard/events/{pastEvent}/prepare')
        ->group(function () {
            Route::get('/', [DashboardEventController::class, 'prepare'])
                ->name('dashboard.events.prepare');

            Route::post('/sponsors', [DashboardEventController::class, 'addSponsor'])
                ->name('dashboard.events.prepare.sponsors.add');

            Route::delete('/sponsors/{company}', [DashboardEventController::class, 'removeSponsor'])
                ->name('dashboard.events.prepare.sponsors.remove');

            Route::post('/participants', [DashboardEventController::class, 'addParticipant'])
                ->name('dashboard.events.prepare.participants.add');

            Route::delete('/participants/{user}', [DashboardEventController::class, 'removeParticipant'])
                ->name('dashboard.events.prepare.participants.remove');

            Route::post('/settings', [DashboardEventController::class, 'saveSettings'])
                ->name('dashboard.events.prepare.settings');

            Route::get('/search-companies', [DashboardEventController::class, 'searchCompanies'])
                ->name('dashboard.events.prepare.search-companies');

            Route::get('/search-users', [DashboardEventController::class, 'searchUsers'])
                ->name('dashboard.events.prepare.search-users');

            Route::post('/complete', [DashboardEventController::class, 'completeEdition'])
                ->name('dashboard.events.prepare.complete');

            Route::get('/stats', [DashboardEventController::class, 'stats'])
                ->name('dashboard.events.stats');
        });

    // Invoices
    Route::prefix('dashboard/invoices')
        ->group(function () {
            Route::get('/', [InvoiceController::class, 'list'])
                ->name('invoices.list');

            Route::post('/{invoiceId}/virement', [InvoiceController::class, 'markAsVirement'])
                ->name('invoices.virement');
        });


    // Reports
    Route::prefix('dashboard/reports')
        ->name('dashboard.reports.')
        ->group(function () {
            Route::get('/', [DashboardReportController::class, 'index'])->name('index');
            Route::get('/export', [DashboardReportController::class, 'export'])->name('export');
        });


    Route::get('/dashboard/staffs/kpi', [DashboardController::class, 'staffIndex'])->name('voyager.dashboard.staff');
    Route::post('/dashboard/staffs/kpi', [DashboardController::class, 'syncStaff']);
    Route::post('/dashboard/staffs/log/{id}', [DashboardController::class, 'confirmStaffLog'])->name('kpi.log.confirm');
    Route::get('/dashboard/staffs/calendar', [DashboardController::class, 'staffCalendarIndex'])->name('voyager.dashboard.staff.calendar');
    Route::post('/dashboard/staffs/calendar', [DashboardController::class, 'syncStaffCalendar']);

    Route::get('/kpi-daily-logs/{id}/edit', [DashboardController::class, 'editStaffDailyLog']);
    Route::post('/kpi-daily-logs/{id}/edit', [DashboardController::class, 'updateStaffDailyLog'])->name('kpi.log.update');


    Route::get('/dashboard/charts/app-opens', [DashboardController::class, 'getAppOpensChartData'])->name('dashboard.app-opens-chart-data');
    Route::get('/dashboard/charts/media-views', [DashboardController::class, 'getMediaViewsByHourChartData'])->name('dashboard.media-views-chart-data');

    // Images
    Route::post('/upload-image-s3/lesson', [ImageController::class, 'uploadLessonImageToS3'])->name('upload.image.s3.lesson');
    Route::post('/upload-image-s3/student-certificate', [ImageController::class, 'uploadStudentCertificateToS3'])->name('upload.image.s3.student');
    Route::delete('/delete-image-s3', [ImageController::class, 'deleteFromS3'])->name('delete.image.s3');


    // Route::get('/upload-video-s3', [VideoController::class, 'edit'])->name('edit.video.s3');
    // Route::post('/upload-video-s3', [VideoController::class, 'uploadToS3'])->name('upload.video.s3');
    // Route::delete('/delete-video-s3', [VideoController::class, 'delete'])->name('delete.video.s3');

    // Lessons
    Route::get('/modules/{moduleId}/lessons/create', [LessonController::class, 'create'])->name('lessons.create');

    Route::get('/modules/{moduleId}/lessons', [LessonController::class, 'index'])->name('modules.lessons.index');
    Route::get('/modules/{moduleId}/lessons/order', [LessonController::class, 'orderView'])->name('modules.lessons.order');
    Route::post('/modules/{moduleId}/lessons/order', [LessonController::class, 'updateOrder'])->name('modules.lessons.order.update');

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
    Route::get('/accounts/{id}', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/accounts/{id}', [AccountController::class, 'update'])->name('account.update');

    Route::post('/accounts/{id}/confirm', [AccountController::class, 'confirm'])->name('account.confirm');
    Route::post('/accounts/{id}/delete', [AccountController::class, 'destroy'])->name('account.delete');
    Route::get('/accounts/{id}/resend', [AccountController::class, 'resend'])->name('account.resend');

    Route::get('/accounts/{id}/sync', [AccountController::class, 'sync'])->name('account.sync');

    Route::get('/accounts/{id}/training-permissions', [TrainingPermissionController::class, 'storeWithUser'])->name('account.training-permissions');
    Route::post('/accounts/{id}/training-permissions', [TrainingPermissionController::class, 'updateWithUser'])->name('account.training-permissions.update');

    // Companies
    Route::get('/companies', [CompanyController::class, 'index'])->name('voyager.companies.index');
    Route::get('/companies/{id}', [CompanyController::class, 'show'])->name('voyager.companies.show');

    Route::get('/companies/{id}/odoo', [CompanyController::class, 'connectOdoo'])->name('companies.odoo.connect');
    Route::get('/companies/{id}/send-emails', [CompanyController::class, 'sendAuthEmails'])->name('companies.emails.send');

    Route::get('/companies/{id}/back-editable', [CompanyController::class, 'backEditable'])->name('companies.back-editable');
    Route::post('/companies/{id}/fill-data', [CompanyController::class, 'fillDataFromTemps'])->name('companies.fill-data');
    Route::post('/companies/{id}/user/{userId}/update-manager', [CompanyController::class, 'updateManager'])->name('companies.manager.update');

    Route::get('/companies/{id}/invite-code', [CompanyController::class, 'generateInviteCode'])->name('companies.invite-code.generate');
    Route::get('/companies/{id}/integrity-check', [CompanyController::class, 'integrityCheck'])->name('companies.integrity-check');
    Route::get('/companies/manager/{id}/register-company-email', [CompanyController::class, 'resendEmailRegisterCompany'])->name('companies.register-company-email');

    Route::get('/sale-assignments', [SaleAssignmentController::class, 'index'])->name('sales.index');
    Route::post('/sale-assignments/{saleId}/user/{userId}', [SaleAssignmentController::class, 'remove'])->name('sales.remove');

    // Products
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/product', [ProductController::class, 'create'])->name('products.create');
    Route::post('/product', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{id}', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::get('/rules', [ProductController::class, 'getRules'])->name('rules.default');
    Route::get('/rules/products', [ProductController::class, 'viewRules'])->name('rules.products');
    Route::get('/rules/products/edit', [ProductController::class, 'editRules'])->name('rules.products.edit');
    Route::post('/rules/products/edit', [ProductController::class, 'updateRules'])->name('rules.products.update');

    // Product predata
    Route::get('/products-predata', [ProductPreDataController::class, 'index'])->name('products.predata.index');
    Route::get('/products-predata/export', [ProductPreDataController::class, 'export'])->name('products.predata.export');
    Route::post('/products-predata/import/preview', [ProductPreDataController::class, 'importPreview'])->name('products.predata.preview');
    Route::post('/products-predata/import/apply', [ProductPreDataController::class, 'importApply'])->name('products.predata.apply');

    // Ads
    Route::prefix('dashboard/video-ads')
        ->group(function () {
            Route::get('/', [DashboardVideoAdsController::class, 'index'])
                ->name('dashboard.video-ads');

            Route::get('/data', [DashboardVideoAdsController::class, 'chartData'])
                ->name('dashboard.video-ads.data');
        });
    Route::prefix('dashboard/video-ads/{videoAd}/targets')
        ->group(function () {
            Route::get('/', [DashboardVideoAdsController::class, 'targets'])
                ->name('dashboard.video-ads.targets');

            Route::post('/', [DashboardVideoAdsController::class, 'storeTargets'])
                ->name('dashboard.video-ads.targets.store');

            Route::delete('/{effectiveFrom}', [DashboardVideoAdsController::class, 'destroyTargets'])
                ->name('dashboard.video-ads.targets.destroy');
        });


    // Videos views
    Route::prefix('reports/video-views')
        ->group(function () {
            Route::get('/', [DashboardVideoViewsController::class, 'index'])
                ->name('dashboard.videos');

            Route::get('/data', [DashboardVideoViewsController::class, 'tableData'])
                ->name('dashboard.videos.data');
        });



    // Sales
    Route::group(['prefix' => 'dashboard/staffs'], function () {
        Route::get('/', [IntEmpTrackerController::class, 'dashboard'])->name('telesales.dashboard');
        Route::get('/summary', [IntEmpTrackerController::class, 'summaryData'])->name('telesales.summary.data');
        Route::get('/calls', [IntEmpTrackerController::class, 'callLogsData'])->name('telesales.calls.data');
        Route::get('/recalls', [IntEmpTrackerController::class, 'recallReminders'])->name('telesales.recalls.data');
        Route::get('/meetings', [IntEmpTrackerController::class, 'meetingReminders'])->name('telesales.meetings.data');
        Route::post('/calls/{id}/meeting', [IntEmpTrackerController::class, 'updateMeeting'])->name('telesales.calls.meeting');
        Route::post('/calls/{id}/customer-type', [IntEmpTrackerController::class, 'defineCustomerType'])->name('telesales.calls.define');

        // Contacts
        Route::post('/contacts', [IntEmpTrackerController::class, 'storeContact'])->name('telesales.contacts.store');
        Route::get('/contacts/sync', [IntEmpTrackerController::class, 'syncContacts'])
            ->name('telesales.contacts.sync')->middleware('cmsAdmin');

        // Devices of salers
        Route::get('/devices', [IntEmpTrackerController::class, 'devices'])
            ->name('telesales.devices')->middleware('cmsAdmin');
        Route::get('/devices/data', [IntEmpTrackerController::class, 'devicesData'])->name('telesales.devices.data');
        Route::get('/devices/alerts', [IntEmpTrackerController::class, 'deviceAlertsData'])->name('telesales.devices.alerts');
        Route::post('/devices', [IntEmpTrackerController::class, 'deviceStore'])->name('telesales.devices.store');
        Route::post('/devices/{id}', [IntEmpTrackerController::class, 'deviceUpdate'])->name('telesales.devices.update');
        Route::delete('/devices/alerts', [IntEmpTrackerController::class, 'deviceAlertsClear'])->name('telesales.devices.alerts.clear');
        Route::delete('/devices/{id}', [IntEmpTrackerController::class, 'deviceDestroy'])->name('telesales.devices.destroy');
        Route::delete('/devices/alerts/{id}', [IntEmpTrackerController::class, 'deviceAlertDestroy'])->name('telesales.devices.alerts.destroy');

        // Install guide
        Route::get('/install-guide', [IntEmpTrackerController::class, 'installGuide'])->name('telesales.install.guide');
        Route::get('/install-guide/download', function () {
            $path = storage_path('app/call-tracker/tracker.zip');
            abort_unless(file_exists($path), 404);
            return response()->download($path, 'tracker.zip');
        })->name('telesales.install.download');
    });

    // Generate
    Route::group(['prefix' => 'generate/document'], function () {
        Route::get('contract', [GenerateDocumentController::class, 'generateContractView'])->name('generate.document.contract');
        Route::post('contract', [GenerateDocumentController::class, 'generateContract'])->name('generate.document.contract.export');
        Route::post('invoice', [GenerateDocumentController::class, 'generateInvoice'])->name('generate.document.invoice.export');
    });


    // Marketing Tools
    Route::get('/tools', [MktToolsController::class, 'index'])->name('mkt.tools');
    Route::post('/tools/short-link', [MktToolsController::class, 'storeShortLink'])->name('mkt.shortlink.store');

    Route::group(['prefix' => 'dashboard/short-links'], function () {
        // Liste des short links
        Route::get('/', [MktShortLinkController::class, 'index'])
            ->name('dashboard.short-links');

        // Tableau de bord de tracking
        Route::get('/tracking', [MktShortLinkController::class, 'tracking'])
            ->name('dashboard.short-links.tracking');

        // Endpoint JSON pour le fetch dynamique des filtres
        Route::get('/tracking/data', [MktShortLinkController::class, 'trackingData'])
            ->name('dashboard.short-links.tracking.data');

        // Édition d'un short link (formulaire à créer selon vos besoins)
        Route::get('/{shortLink}/edit', [MktShortLinkController::class, 'edit'])
            ->name('dashboard.short-links.edit');
        Route::put('/{shortLink}', [MktShortLinkController::class, 'update'])
            ->name('dashboard.short-links.update');
    });

    // Guide
    Route::get('guides/create-media', [GuideController::class, 'createMedia'])
        ->name('guides.create-media');

    // Daily question
    Route::prefix('daily-questions')
        ->name('voyager.daily-questions.')
        ->group(function () {
            Route::get('/', [DailyQuestionController::class, 'index'])->name('index');
            Route::get('/create', [DailyQuestionController::class, 'create'])->name('create');
            Route::post('/', [DailyQuestionController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [DailyQuestionController::class, 'edit'])->name('edit');
            Route::match(['put', 'patch'], '/{id}', [DailyQuestionController::class, 'update'])->name('update');
        });

    // Podcast
    Route::prefix('podcasts')
        ->name('voyager.podcasts.')
        ->group(function () {
            Route::get('/', [PodcastController::class, 'index'])->name('index');
            Route::get('/create', [PodcastController::class, 'create'])->name('create');
            Route::post('/', [PodcastController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [PodcastController::class, 'edit'])->name('edit');
            Route::match(['put', 'patch'], '/{id}', [PodcastController::class, 'update'])->name('update');
        });

    // Infographic
    Route::prefix('infographics')
        ->name('infographics.')
        ->group(function () {
            Route::get('/', [InfographicController::class, 'index'])->name('index');
            Route::get('/create', [InfographicController::class, 'create'])->name('create');
            Route::post('/', [InfographicController::class, 'store'])->name('store');
            Route::get('/search-videos', [InfographicController::class, 'searchVideos'])->name('search-videos');
            Route::get('/{id}/edit', [InfographicController::class, 'edit'])->name('edit');
            Route::put('/{id}', [InfographicController::class, 'update'])->name('update');
            Route::delete('/{id}', [InfographicController::class, 'destroy'])->name('destroy');
        });
    Route::get('dashboard/daily-actions', [DashboardDailyActionController::class, 'index'])
        ->name('dashboard.daily-actions');

    // Test notification
    Route::prefix('notifications')
        ->name('notifications.')
        ->group(function () {
            Route::get('/test', [NotificationTestController::class, 'index'])->name('test');
            Route::get('/test/devices/{userId}', [NotificationTestController::class, 'devices'])->name('test.devices');
            Route::get('/test/targets/{type}', [NotificationTestController::class, 'targets'])->name('test.targets');
            Route::post('/test', [NotificationTestController::class, 'send'])->name('test.send');
        });
});


// Demo
Route::get('/00e7eb2a475/chat', [AiController::class, 'view']);
Route::post('/api/ask-notebook', [AiController::class, 'ask']);

// Route::get('/test/auto-programme', [GeneratePdfController::class, 'testAutotProgFilePdf']);
// Route::get('/test-fifpl-cert', [TestingController::class, 'testFifplCert']);
// Route::get('/test-meeting', [MeetingController::class, 'createMeeting']);
// Route::get('/check-odoo/export-2025', [CheckOdooInvoiceController::class, 'exportInvoicesToExcel2025']);

// Quizz
Route::get('/quiz/{slug}', [QuizController::class, 'show'])->name('quiz.show');
Route::post('/quiz/{slug}/submit', [QuizController::class, 'submit'])->name('quiz.submit');
Route::post('/submissions/{submission}/contact', [QuizController::class, 'contact'])->name('quiz.contact');

// Video Ads partagé : HORS de tout middleware admin/auth ----
Route::prefix('dashboard/video-ads/shared/{company}')
    ->group(function () {
        Route::get('/', [DashboardVideoAdsController::class, 'shared'])
            ->name('dashboard.video-ads.shared');

        Route::get('/data', [DashboardVideoAdsController::class, 'sharedChartData'])
            ->name('dashboard.video-ads.shared.data');
    });

// ---- Rapport partagé : HORS de tout middleware admin/auth ----
Route::get('medias/shared/{code}', [MediaController::class, 'shared'])
    ->name('medias.shared');
Route::get('medias/shared/{code}/search-experts', [MediaController::class, 'sharedSearchExperts'])
    ->name('medias.shared.search-experts');
Route::get('medias/shared/{code}/search-videos', [MediaController::class, 'sharedSearchVideos'])
    ->name('medias.shared.search-videos');

// Redirection publique des short links (idéalement sur le domaine go.orthomaster.fr)
Route::get('/sh/{code}', [MktToolsController::class, 'redirect'])->name('mkt.shortlink.redirect');
Route::get('/open/{path?}', function (Request $request, ?string $path = null) {
    $query = $request->getQueryString(); // Ex: screen=patient&id=123
    $suffix = $path ?? '';
    if ($query) {
        $suffix .= '?' . $query;
    }

    return view('deeplink', ['suffix' => $suffix]);
})->where('path', '.*');
