<?php

namespace App\Providers;

use App\Voyager\CustomSchemaManager;
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

        \App\Models\OldContent::observe(\App\Observers\OldContentAiSearchObserver::class);
        \App\Models\Video::observe(\App\Observers\VideoAiSearchObserver::class);
        \App\Models\BookingEvent::observe(\App\Observers\BookingEventChatObserver::class);
    }
}
