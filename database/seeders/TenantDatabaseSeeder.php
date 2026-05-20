<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Seed roles and permissions ────────────────────────────────────────
        $permissions = [
            'manage_academic_sessions',
            'manage_terms',
            'manage_class_levels',
            'manage_class_arms',
            'manage_subjects',
            'manage_grading_scales',
            'manage_school_settings',
            'manage_teachers',
            'manage_students',
            'create_exams',
            'edit_exams',
            'publish_exams',
            'view_exams',
            'view_results',
            'publish_results',
            'compute_results',
            'manage_questions',
            'manage_attendance',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'tenant',
            ]);
        }

        $adminRole = Role::firstOrCreate([
            'name' => 'school_admin',
            'guard_name' => 'tenant',
        ]);
        $adminRole->syncPermissions($permissions);

        $teacherRole = Role::firstOrCreate([
            'name' => 'teacher',
            'guard_name' => 'tenant',
        ]);
        $teacherRole->syncPermissions([
            'create_exams',
            'edit_exams',
            'view_exams',
            'manage_questions',
            'view_results',
            'manage_attendance',
        ]);

        $studentRole = Role::firstOrCreate([
            'name' => 'student',
            'guard_name' => 'tenant',
        ]);
        $studentRole->syncPermissions(['view_exams', 'view_results']);

        // ── Seed default class levels ─────────────────────────────────────────
        $classLevels = [
            ['name' => 'JSS 1'],
            ['name' => 'JSS 2'],
            ['name' => 'JSS 3'],
            ['name' => 'SS 1'],
            ['name' => 'SS 2'],
            ['name' => 'SS 3'],
        ];

        foreach ($classLevels as $level) {
            ClassLevel::updateOrCreate(
                ['name' => $level['name']], // Search by name
                ['slug' => Str::slug($level['name'], '')]
            );
        }

        // ── Seed default current academic context ────────────────────────────
        $currentSession = AcademicSession::where('is_current', true)->first();

        if (! $currentSession) {
            $startYear = now()->month >= 9 ? now()->year : now()->year - 1;
            $endYear = $startYear + 1;

            $currentSession = AcademicSession::create([
                'id' => Str::uuid()->toString(),
                'name' => "{$startYear}/{$endYear}",
                'start_date' => now()->setDate($startYear, 9, 1)->startOfDay(),
                'end_date' => now()->setDate($endYear, 8, 31)->startOfDay(),
                'is_current' => true,
            ]);
        }

        if (! $currentSession->terms()->where('is_current', true)->exists()) {
            $currentSession->terms()->create([
                'id' => Str::uuid()->toString(),
                'name' => 'First Term',
                'start_date' => now()->subMonths(3)->startOfDay(),
                'end_date' => now()->addMonths(3)->startOfDay(),
                'is_current' => true,
            ]);
        }

        // ── Seed default subjects ─────────────────────────────────────────────
        $subjects = [
            'Mathematics',
            'English Language',
            'Physics',
            'Chemistry',
            'Biology',
            'Agricultural Science',
            'Geography',
            'History',
            'Civic Education',
            'Economics',
            'Government',
            'Literature in English',
            'Further Mathematics',
            'Computer Science',
        ];

        foreach ($subjects as $name) {
            DB::table('subjects')->updateOrInsert(
                ['name' => $name],
                [
                    'id' => Str::uuid()->toString(),
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        // ── Seed default grading scale ────────────────────────────────────────
        DB::table('grading_scales')->updateOrInsert(
            ['is_default' => true],
            [
                'id' => Str::uuid()->toString(),
                'name' => 'Standard Nigerian Grading',
                'is_default' => true,
                'grades' => json_encode([
                    [
                        'label' => 'A1',
                        'min_score' => 75,
                        'max_score' => 100,
                        'remark' => 'Excellent',
                    ],
                    [
                        'label' => 'B2',
                        'min_score' => 70,
                        'max_score' => 74,
                        'remark' => 'Very Good',
                    ],
                    [
                        'label' => 'B3',
                        'min_score' => 65,
                        'max_score' => 69,
                        'remark' => 'Good',
                    ],
                    [
                        'label' => 'C4',
                        'min_score' => 60,
                        'max_score' => 64,
                        'remark' => 'Credit',
                    ],
                    [
                        'label' => 'C5',
                        'min_score' => 55,
                        'max_score' => 59,
                        'remark' => 'Credit',
                    ],
                    [
                        'label' => 'C6',
                        'min_score' => 50,
                        'max_score' => 54,
                        'remark' => 'Credit',
                    ],
                    [
                        'label' => 'D7',
                        'min_score' => 45,
                        'max_score' => 49,
                        'remark' => 'Pass',
                    ],
                    [
                        'label' => 'E8',
                        'min_score' => 40,
                        'max_score' => 44,
                        'remark' => 'Pass',
                    ],
                    [
                        'label' => 'F9',
                        'min_score' => 0,
                        'max_score' => 39,
                        'remark' => 'Fail',
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // ── Seed school settings ──────────────────────────────────────────────
        $settings = [
            ['key' => 'ca_weight',                'value' => '40',                          'type' => 'integer'],
            ['key' => 'exam_weight',              'value' => '60',                          'type' => 'integer'],
            ['key' => 'allow_result_viewing',     'value' => 'false',                       'type' => 'boolean'],
            ['key' => 'school_name',              'value' => tenant('name') ?? 'School',    'type' => 'string'],
            ['key' => 'terms_per_session',        'value' => '3',                           'type' => 'integer'],
            ['key' => 'result_approval_required', 'value' => 'true',                        'type' => 'boolean'],
            ['key' => 'assessment_max_score',     'value' => '50',                          'type' => 'integer'],
            ['key' => 'exam_max_score',           'value' => '100',                         'type' => 'integer'],
        ];

        foreach ($settings as $setting) {
            DB::table('school_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'id' => Str::uuid()->toString(),
                    'key' => $setting['key'],
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // ── Provision school admin from onboarding data ───────────────────────
        // Admin credentials were stored in tenant settings during CreateTenantAction.
        // We read them here, create the user, then clear them for security.
        $rawSettings = tenant('settings');
        $parsedSettings = is_string($rawSettings) ? json_decode($rawSettings, true) : $rawSettings;

        $onboardingAdmin = $parsedSettings['onboarding_admin'] ?? null;

        if ($onboardingAdmin) {
            $admin = User::firstOrCreate(
                ['email' => $onboardingAdmin['email']],
                [
                    'id' => Str::uuid()->toString(),
                    'first_name' => $onboardingAdmin['first_name'],
                    'last_name' => $onboardingAdmin['last_name'],
                    'email' => $onboardingAdmin['email'],
                    'password' => Hash::make($onboardingAdmin['password']),
                    'role' => 'school_admin',
                    'is_active' => true,
                ],
            );

            $admin->assignRole('school_admin');

            // Clear sensitive credentials from settings after use —
            // they served their purpose and should not persist.
            // We must use the central connection explicitly because the default
            // connection inside a tenant context points to the tenant's DB,
            // not the central DB where the tenants table lives.
            $currentTenant = tenant();
            $cleanedSettings = collect($currentTenant->settings ?? [])
                ->except('onboarding_admin')
                ->toArray();

            DB::connection(config('tenancy.database.central_connection'))
                ->table('tenants')
                ->where('id', $currentTenant->getTenantKey())
                ->update(['settings' => json_encode($cleanedSettings)]);

            // Write to central index so login can resolve tenant without header
            DB::connection(config('tenancy.database.central_connection'))
                ->table('tenant_user_index')
                ->updateOrInsert(
                    ['email' => $admin->email, 'tenant_id' => tenant('id')],
                    [
                        'role' => 'school_admin',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
        }
    }
}
