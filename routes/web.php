<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/up', fn () => response()->json(['message' => 'ok']));

Route::get('/trigger-central-fresh', function () {
    if (request('secret') !== 'super-secret-key-123') {
        abort(403, 'Unauthorized');
    }

    try {
        // This drops all central tables, runs the updated migrations, and seeds it.
        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Central database rebuilt and seeded successfully.',
            'output'  => Artisan::output(), 
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});