<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Envoi des rappels missions (toutes les 5 minutes)
Schedule::job(new \App\Jobs\Mission\SendMissionRemindersJob)
    ->everyFiveMinutes()
    ->name('send-mission-reminders')
    ->withoutOverlapping();

// Expirer les missions dont expires_at est dépassé (tous les jours à 2h)
Schedule::call(function () {
    \App\Models\Mission::published()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->update(['status' => 'archived']);
})->dailyAt('02:00')->name('expire-missions');
