<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

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

// Tick active exam sessions to detect stale heartbeats
Schedule::command('exams:tick-active-sessions')
    ->everyFifteenSeconds()
    ->onFailure(function () {
        Log::channel('slack')->error('Heartbeat tick failed');
    });

// Auto-submit exam attempts whose individual timer has expired
Schedule::command('exams:auto-submit-expired')
    ->everyMinute()
    ->onFailure(function () {
        Log::channel('slack')->error('Auto-submit expired exams failed');
    });

// Complete exams whose window has closed
Schedule::command('exams:complete-expired')
    ->everyMinute()
    ->onFailure(function () {
        Log::channel('slack')->error('Complete expired exams failed');
    });
