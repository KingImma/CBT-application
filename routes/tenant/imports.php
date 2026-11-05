<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\ImportJobStreamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Import job progress (server-sent events)
|--------------------------------------------------------------------------
|
| Shared by the student and teacher importers. The controller itself verifies
| that the job belongs to the current tenant, so one route covers both.
|
*/

Route::get('imports/{importJob}/stream', ImportJobStreamController::class)
    ->middleware('role:school_admin,tenant')
    ->name('imports.stream');