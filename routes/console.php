<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reports
Schedule::command('reports:daily-app-opens')
    ->dailyAt('07:00')
    ->timezone('Europe/Paris');

Schedule::command('reports:daily-app-opens')
    ->dailyAt('12:00')
    ->timezone('Europe/Paris');

Schedule::command('reports:daily-app-opens')
    ->dailyAt('18:00')
    ->timezone('Europe/Paris');

Schedule::command('reports:user-agents')
    ->dailyAt('02:00')
    ->timezone('Europe/Paris');

// Daily actions
Schedule::command('notifications:daily-question')
    ->dailyAt('18:50')
    ->timezone('Europe/Paris');

Schedule::command('notifications:new-podcast')
    ->dailyAt('18:55')
    ->timezone('Europe/Paris');

Schedule::command('notifications:new-infographic')
    ->dailyAt('19:00')
    ->timezone('Europe/Paris');

// FIFPL
Schedule::command('fifpl:pre-invoices')
    ->dailyAt('17:00')
    ->timezone('Europe/Paris');
Schedule::command('fifpl:send-invoices')
    ->dailyAt('08:15')
    ->timezone('Europe/Paris');

// Sales
Schedule::command('sales:update-assignments')
    ->dailyAt('01:00')
    ->timezone('Europe/Paris');

// Company - Manager
Schedule::command('emails:remind-managers')
    ->fridays()
    ->at('11:00')
    ->timezone('Europe/Paris');

// Ad Provider
Schedule::command('emails:ad-provider')
    ->dailyAt('10:00')
    ->timezone('Europe/Paris');
Schedule::command('video-ads:snapshot')
    ->monthlyOn(now()->endOfMonth()->day, '23:00')
    ->timezone('Europe/Paris');

// AI Search
Schedule::command('ai-search:index')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('queue:work --queue=ai-search --stop-when-empty')->everyMinute()->withoutOverlapping();

// Booking
Schedule::command('booking:expire-requests')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('booking:meet-sweep')->everyTenMinutes()->withoutOverlapping();