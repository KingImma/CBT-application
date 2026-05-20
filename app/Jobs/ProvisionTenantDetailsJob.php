<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/*
 * 1. What it is: The finalized `ProvisionTenantResourcesJob`.
 * 2. What it does in a nutshell: It provisions the admin user and elegantly loops through an array of dynamic settings (like the actual school name, chosen term system, and grading scale) to overwrite the generic defaults created by the Seeder.
 * 3. Why this was chosen: It isolates all dynamic database mutations into a single background process. The Seeder remains a pure "factory reset" tool, and the Job acts as the "customizer."
 * 4. Expected deliverables and alternatives: A perfectly tailored school environment based on frontend input. The alternative is writing individual `update()` queries for every single setting, which is repetitive and harder to maintain.
 */

class ProvisionTenantDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int,mixed>  $adminData
     * @param  array<int,mixed>  $curriculumData
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly array $adminData,
        public readonly array $curriculumData,
    ) {}

    public function handle(): void
    {
        $this->tenant->run(function () {
            // ── 1. Create the Admin User & Sync to Central Index ──────────────
            $admin = User::firstOrCreate(
                ['email' => $this->adminData['email']],
                [
                    'id' => Str::uuid()->toString(),
                    'first_name' => $this->adminData['first_name'],
                    'last_name' => $this->adminData['last_name'],
                    'email' => $this->adminData['email'],
                    'phone' => $this->adminData['phone'] ?? null,
                    'password' => Hash::make($this->adminData['password']),
                    'role' => 'school_admin',
                    'is_active' => true,
                ],
            );

            $admin->assignRole('school_admin');

            DB::connection(config('tenancy.database.central_connection'))
                ->table('tenant_user_index')
                ->updateOrInsert(
                    [
                        'email' => $admin->email,
                        'tenant_id' => $this->tenant->id,
                    ],
                    [
                        'role' => 'school_admin',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

            // ── 2. Apply Custom Class Levels ──────────────────────────────────
            if (! empty($this->curriculumData['grades'])) {
                ClassLevel::truncate();

                foreach ($this->curriculumData['grades'] as $gradeName) {
                    ClassLevel::create([
                        'name' => $gradeName,
                        'slug' => Str::slug($gradeName, ''),
                    ]);
                }
            }

            // ── 3. Handle Relational Grading Scales ───────────────────────────
            if (! empty($this->curriculumData['gradingScale'])) {
                // First, remove the default flag from all existing scales seeded by the DB
                DB::table('grading_scales')->update(['is_default' => false]);

                // Assuming the frontend sends a name like "WAEC Standard" or "GPA"
                $scaleName = $this->curriculumData['gradingScale'];

                // Check if this scale exists in the database
                $exists = DB::table('grading_scales')->where('name', $scaleName)->exists();

                if ($exists) {
                    // Make the selected existing scale the default
                    DB::table('grading_scales')
                        ->where('name', $scaleName)
                        ->update(['is_default' => true]);
                } else {
                    DB::table('grading_scales')->insert([
                        'id' => Str::uuid()->toString(),
                        'name' => $scaleName,
                        'is_default' => true,
                        'grades' => json_encode([]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // ── 4. Batch Update Key-Value Settings ────────────────────────────
            $settingsToUpdate = [
                'school_name' => $this->tenant->name,
            ];

            if (! empty($this->curriculumData['term_system'])) {
                $settingsToUpdate['terms_per_session'] = (string) filter_var(
                    $this->curriculumData['term_system'],
                    FILTER_SANITIZE_NUMBER_INT,
                );
            }

            foreach ($settingsToUpdate as $key => $value) {
                DB::table('school_settings')
                    ->where('key', $key)
                    ->update(['value' => $value]);
            }
        });
    }
}
