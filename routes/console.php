<?php 

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

Schedule::command('horizon:snapshot')->everyFiveMinutes();
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

// Auto-activate scheduled exams whose scheduled_start has passed
Schedule::command('exams:activate-scheduled')
    ->everyMinute()
    ->onFailure(function () {
        Log::channel('slack')->error('Activate scheduled exams failed');
    });

// Auto-submit exam attempts whose individual or session timer has expired
Schedule::command('exams:auto-submit-expired')
    ->everyMinute()
    ->onFailure(function () {
        Log::channel('slack')->error('Auto-submit expired exams failed');
    });

// End exam sessions that have passed their scheduled end time
Schedule::command('exams:end-expired-sessions')
    ->everyMinute()
    ->onFailure(function () {
        Log::channel('slack')->error('End expired exam sessions failed');
    });
