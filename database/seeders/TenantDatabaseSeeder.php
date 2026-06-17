<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

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
    }
}
