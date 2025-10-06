<?php

namespace App\Providers;

use App\Models\OpeningHour;
use App\Voyager\CustomSchemaManager;
use Illuminate\Support\ServiceProvider;
use TCG\Voyager\Database\Schema\SchemaManager;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Request;

use App\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

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
    public function boot(): void {}
}
