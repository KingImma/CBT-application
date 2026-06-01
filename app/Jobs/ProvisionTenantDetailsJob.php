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
            $admin = $this->createAdminUser();
            $this->applyCustomClassLevels();
            $this->applyGradingScale();
            $this->applySettings();
        });
    }

    private function createAdminUser(): User
    {
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

        return $admin;
    }

    private function applyCustomClassLevels(): void
    {
        if (empty($this->curriculumData['grades'])) {
            return;
        }

        ClassLevel::truncate();

        foreach ($this->curriculumData['grades'] as $gradeName) {
            ClassLevel::create([
                'name' => $gradeName,
                'slug' => Str::slug($gradeName, ''),
            ]);
        }
    }

    private function applyGradingScale(): void
    {
        if (empty($this->curriculumData['gradingScale'])) {
            return;
        }

        DB::table('grading_scales')->update(['is_default' => false]);

        $scaleName = $this->curriculumData['gradingScale'];
        $exists = DB::table('grading_scales')->where('name', $scaleName)->exists();

        if ($exists) {
            DB::table('grading_scales')
                ->where('name', $scaleName)
                ->update(['is_default' => true]);

            return;
        }

        DB::table('grading_scales')->insert([
            'id' => Str::uuid()->toString(),
            'name' => $scaleName,
            'is_default' => true,
            'grades' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function applySettings(): void
    {
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
    }
}
