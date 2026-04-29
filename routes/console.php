<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Prune expired Sanctum tokens from central database (SuperAdmin tokens)
Schedule::command('sanctum:prune-expired --hours=24')
    ->daily()
    ->onFailure(function () {
        Log::channel('slack')->error('Central token pruning failed');
    });

// Prune expired Sanctum tokens from all tenant databases
Schedule::command('tenants:prune-expired-tokens --hours=24')
    ->daily()
    ->onFailure(function () {
        Log::channel('slack')->error('Tenant token pruning failed');
    });