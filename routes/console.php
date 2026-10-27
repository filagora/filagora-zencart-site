<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sitemap:generate')->dailyAt('03:00')->withoutOverlapping();

Schedule::command('notifications:prune')->dailyAt('02:40')->withoutOverlapping();

Schedule::command('filagora:messages:purge-trashed')->dailyAt('02:20')->withoutOverlapping();
