<?php

use App\Services\AutomatedAccountUpdater;
use App\Services\Subscriptions\DailyUpdater;
use App\Services\SubscriptionUpdater;
use App\Services\WeeklySummaryProcessor;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function() {
    app(AutomatedAccountUpdater::class)->handle();
})->dailyAt('00:01');

Schedule::call(function() {
    app(DailyUpdater::class)->handle();
})->dailyAt('00:30');

Schedule::call(function() {
    app(WeeklySummaryProcessor::class)->handle();
})->sundays()->at('08:00');
