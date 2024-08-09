<?php

use App\Services\AutomatedAccountUpdater;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function() {
    app(AutomatedAccountUpdater::class)->handle();
})->dailyAt('00:01');
