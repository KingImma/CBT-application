<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/up', fn () => response()->json(['message' => 'ok']));

Route::get('/trigger-tenant-seed', function () {
    // Simple security check
    if (request('secret') !== 'super-secret-key-123') {
        abort(403, 'Unauthorized');
    }

    try {
        // Run the seeder for all existing tenants
        Artisan::call('tenants:seed', [
            '--class' => 'TenantDatabaseSeeder'
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tenants seeded successfully.',
            // This returns the actual terminal text you would normally see
            'output'  => Artisan::output(), 
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});