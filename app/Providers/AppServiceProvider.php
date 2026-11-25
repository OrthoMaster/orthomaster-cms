<?php

namespace App\Providers;

use App\Voyager\CustomSchemaManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use TCG\Voyager\Database\Schema\SchemaManager;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SchemaManager::class, CustomSchemaManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->bound('VoyagerAuth')) {
            Cache::remember('voyager_settings', 600, function () {
                return \TCG\Voyager\Models\Setting::orderBy('order')->get();
            });
        }

        // Congrès : toute la salle peut partager une même IP WiFi, la limite ne se compte donc pas par IP.
        RateLimiter::for('congres-join', fn (Request $request) => Limit::perMinute(600)->by('congres-join|' . $request->route('code')));
        RateLimiter::for('congres-operator', fn (Request $request) => Limit::perMinute(240)->by('congres-operator|' . $request->ip()));
        RateLimiter::for('congres-question', fn (Request $request) => Limit::perMinute(10)->by(
            'congres-question|' . sha1((string) ($request->header('X-Participant-Token') ?: $request->ip()))
        ));

        \App\Models\OldContent::observe(\App\Observers\OldContentAiSearchObserver::class);
        \App\Models\Video::observe(\App\Observers\VideoAiSearchObserver::class);
        \App\Models\BookingEvent::observe(\App\Observers\BookingEventChatObserver::class);
        // E-mails des rendez-vous experts (demande, réponse, annulation, lien de visio).
        \App\Models\BookingEvent::observe(\App\Observers\BookingEventMailObserver::class);
        // Factures (VE) et avoirs (RVE) des rendez-vous experts, numérotés par Odoo.
        \App\Models\BookingEvent::observe(\App\Observers\BookingEventInvoiceObserver::class);
    }
}
