<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/up', fn () => response()->json(['message' => 'ok']));

Route::get('/trigger-tenant-seed', function () {
    if (request('secret') !== 'super-secret-key-123') {
        abort(403, 'Unauthorized');
    }

    try {
        Artisan::call('tenants:migrate-fresh', [
            '--seed' => true,
            '--force' => true
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tenant databases rebuilt and seeded successfully.',
            'output'  => Artisan::output(), 
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});