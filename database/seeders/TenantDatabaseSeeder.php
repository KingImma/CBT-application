<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Seed roles and permissions ────────────────────────────────────────
        $permissions = [
            "manage_academic_sessions",
            "manage_terms",
            "manage_class_levels",
            "manage_class_arms",
            "manage_subjects",
            "manage_grading_scales",
            "manage_school_settings",
            "manage_teachers",
            "manage_students",
            "create_exams",
            "edit_exams",
            "publish_exams",
            "view_exams",
            "view_results",
            "publish_results",
            "compute_results",
            "manage_questions",
            "manage_attendance",
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                "name" => $perm,
                "guard_name" => "tenant",
            ]);
        }

        $adminRole = Role::firstOrCreate([
            "name" => "school_admin",
            "guard_name" => "tenant",
        ]);
        $adminRole->syncPermissions($permissions);

        $teacherRole = Role::firstOrCreate([
            "name" => "teacher",
            "guard_name" => "tenant",
        ]);
        $teacherRole->syncPermissions([
            "create_exams",
            "edit_exams",
            "view_exams",
            "manage_questions",
            "view_results",
            "manage_attendance",
        ]);

        $studentRole = Role::firstOrCreate([
            "name" => "student",
            "guard_name" => "tenant",
        ]);
        $studentRole->syncPermissions(["view_exams", "view_results"]);

        // ── Seed default class levels ─────────────────────────────────────────
        $classLevels = [
            ["name" => "JSS 1", "slug" => "jss1", "order" => 1, "category" => "junior"],
            ["name" => "JSS 2", "slug" => "jss2", "order" => 2, "category" => "junior"],
            ["name" => "JSS 3", "slug" => "jss3", "order" => 3, "category" => "junior"],
            ["name" => "SS 1",  "slug" => "ss1",  "order" => 4, "category" => "senior"],
            ["name" => "SS 2",  "slug" => "ss2",  "order" => 5, "category" => "senior"],
            ["name" => "SS 3",  "slug" => "ss3",  "order" => 6, "category" => "senior"],
        ];

        foreach ($classLevels as $level) {
            \Illuminate\Support\Facades\DB::table(
                "class_levels",
            )->updateOrInsert(
                ["name" => $level["name"]],
                array_merge($level, [
                    "id" => Str::uuid()->toString(),
                    "created_at" => now(),
                    "updated_at" => now(),
                ]),
            );
        }

        // ── Seed default subjects ─────────────────────────────────────────────
        $subjects = [
            "Mathematics",
            "English Language",
            "Physics",
            "Chemistry",
            "Biology",
            "Agricultural Science",
            "Geography",
            "History",
            "Civic Education",
            "Economics",
            "Government",
            "Literature in English",
            "Further Mathematics",
            "Computer Science",
        ];

        foreach ($subjects as $name) {
            \Illuminate\Support\Facades\DB::table("subjects")->updateOrInsert(
                ["name" => $name],
                [
                    "id" => Str::uuid()->toString(),
                    "name" => $name,
                    "slug"       => Str::slug($name),
                    "is_active" => true,
                    "created_at" => now(),
                    "updated_at" => now(),
                ],
            );
        }

        // ── Seed default grading scale ────────────────────────────────────────
        \Illuminate\Support\Facades\DB::table("grading_scales")->updateOrInsert(
            ["is_default" => true],
            [
                "id" => Str::uuid()->toString(),
                "name" => "Standard Nigerian Grading",
                "is_default" => true,
                "grades" => json_encode([
                    [
                        "label" => "A1",
                        "min_score" => 75,
                        "max_score" => 100,
                        "remark" => "Excellent",
                    ],
                    [
                        "label" => "B2",
                        "min_score" => 70,
                        "max_score" => 74,
                        "remark" => "Very Good",
                    ],
                    [
                        "label" => "B3",
                        "min_score" => 65,
                        "max_score" => 69,
                        "remark" => "Good",
                    ],
                    [
                        "label" => "C4",
                        "min_score" => 60,
                        "max_score" => 64,
                        "remark" => "Credit",
                    ],
                    [
                        "label" => "C5",
                        "min_score" => 55,
                        "max_score" => 59,
                        "remark" => "Credit",
                    ],
                    [
                        "label" => "C6",
                        "min_score" => 50,
                        "max_score" => 54,
                        "remark" => "Credit",
                    ],
                    [
                        "label" => "D7",
                        "min_score" => 45,
                        "max_score" => 49,
                        "remark" => "Pass",
                    ],
                    [
                        "label" => "E8",
                        "min_score" => 40,
                        "max_score" => 44,
                        "remark" => "Pass",
                    ],
                    [
                        "label" => "F9",
                        "min_score" => 0,
                        "max_score" => 39,
                        "remark" => "Fail",
                    ],
                ]),
                "created_at" => now(),
                "updated_at" => now(),
            ],
        );

        // ── Seed school settings ──────────────────────────────────────────────
        $settings = [
            ["key" => "ca_weight",                "value" => "40",                          "type" => "integer"],
            ["key" => "exam_weight",              "value" => "60",                          "type" => "integer"],
            ["key" => "allow_result_viewing",     "value" => "false",                       "type" => "boolean"],
            ["key" => "school_name",              "value" => tenant("name") ?? "School",    "type" => "string"],
            ["key" => "terms_per_session",        "value" => "3",                           "type" => "integer"],
            ["key" => "result_approval_required", "value" => "true",                        "type" => "boolean"],
        ];

        foreach ($settings as $setting) {
            \Illuminate\Support\Facades\DB::table("school_settings")->updateOrInsert(
                ["key" => $setting["key"]],
                [
                    "id"         => Str::uuid()->toString(),
                    "key"        => $setting["key"],
                    "value"      => $setting["value"],
                    "type"       => $setting["type"], 
                    "created_at" => now(),
                    "updated_at" => now(),
                ]
            );
        }

        // ── Provision school admin from onboarding data ───────────────────────
        // Admin credentials were stored in tenant settings during CreateTenantAction.
        // We read them here, create the user, then clear them for security.
        $rawSettings = tenant("settings");
        $parsedSettings = is_string($rawSettings) ? json_decode($rawSettings, true) : $rawSettings;

        $onboardingAdmin = $parsedSettings["onboarding_admin"] ?? null;

        if ($onboardingAdmin) {
            $admin = User::firstOrCreate(
                ["email" => $onboardingAdmin["email"]],
                [
                    "id" => Str::uuid()->toString(),
                    "first_name" => $onboardingAdmin["first_name"],
                    "last_name" => $onboardingAdmin["last_name"],
                    "email" => $onboardingAdmin["email"],
                    "password" => Hash::make($onboardingAdmin["password"]),
                    "role"       => "school_admin",
                    "is_active" => true,
                ],
            );

            $admin->assignRole("school_admin");

            // Clear sensitive credentials from settings after use —
            // they served their purpose and should not persist.
            // We must use the central connection explicitly because the default
            // connection inside a tenant context points to the tenant's DB,
            // not the central DB where the tenants table lives.
            $currentTenant = tenant();
            $cleanedSettings = collect($currentTenant->settings ?? [])
                ->except("onboarding_admin")
                ->toArray();

            DB::connection(config("tenancy.database.central_connection"))
                ->table("tenants")
                ->where("id", $currentTenant->getTenantKey())
                ->update(["settings" => json_encode($cleanedSettings)]);
        }
    }
}
