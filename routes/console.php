<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

//Schedule::command('queue:work --stop-when-empty --timeout=120 --memory=128')
//    ->everyMinute()
//    ->withoutOverlapping();

Schedule::call(function () {
    \App\Models\RealtimeConnection::pruneStale();
})->everyMinute();
