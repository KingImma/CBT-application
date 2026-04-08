<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/up', fn () => response()->json(['message' => 'ok']));

Route::get('/trigger-tenant-seed', function () {
    if (request('secret') !== 'super-secret-key-123') {
        abort(403, 'Unauthorized');
    }

    try {
        // 1. Wipe and re-migrate the tenant databases
        Artisan::call('tenants:migrate-fresh', [
            '--no-interaction' => true,
        ]);
        $migrationOutput = Artisan::output();

        // 2. Run the seeder separately
        Artisan::call('tenants:seed', [
            '--class'           => 'TenantDatabaseSeeder',
            '--no-interaction'  => true,
        ]);
        $seederOutput = Artisan::output();

        return response()->json([
            'status'           => 'success',
            'message'          => 'Tenant databases rebuilt and seeded successfully.',
            'migration_output' => $migrationOutput,
            'seeder_output'    => $seederOutput,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});
