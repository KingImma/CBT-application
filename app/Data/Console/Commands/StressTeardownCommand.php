<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class StressTeardownCommand extends Command
{
    protected $signature = 'stress:teardown
        {--input=storage/app/stress-provision.json : Path to provisioning data}
        {--force : Skip confirmation}';

    protected $description = 'Tear down stress test environment';

    public function handle(): int
    {
        $inputPath = $this->option('input');

        if (! file_exists($inputPath)) {
            $this->warn("Provisioning data not found at {$inputPath}");

            return self::SUCCESS;
        }

        $data = json_decode(file_get_contents($inputPath), true);

        // Stop the dev server
        if (! empty($data['server_pid'])) {
            $this->info("Stopping server (PID: {$data['server_pid']})...");
            exec("kill {$data['server_pid']} 2>/dev/null");
        }

        // Find and clean up tenant
        $tenant = Tenant::find($data['tenant_id'] ?? '');

        if ($tenant) {
            $this->info('Dropping tenant database...');
            try {
                $tenant->database()->manager()->deleteDatabase($tenant);
            } catch (\Exception $e) {
                $this->warn("Could not drop database: {$e->getMessage()}");
            }

            $this->info('Deleting tenant record...');
            $tenant->delete();
        }

        // Remove provisioning file
        @unlink($inputPath);

        $this->info('Stress test environment torn down successfully.');

        return self::SUCCESS;
    }
}
