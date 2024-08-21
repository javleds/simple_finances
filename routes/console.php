<?php

use App\Services\AutomatedAccountUpdater;
use App\Services\SubscriptionUpdater;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function() {
    app(AutomatedAccountUpdater::class)->handle();
})->dailyAt('00:01');

Schedule::call(function() {
    app(SubscriptionUpdater::class)->handle();
})->dailyAt('00:30');
