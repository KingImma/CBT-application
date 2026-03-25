<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $currentYear = now()->year;
        $sessionName = $currentYear . '/' . ($currentYear + 1);

        // 1. Class levels
        DB::table('class_levels')->insert([
            ['id' => Str::uuid(), 'name' => 'JSS 1', 'slug' => 'jss1', 'order' => 1, 'category' => 'junior', 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'JSS 2', 'slug' => 'jss2', 'order' => 2, 'category' => 'junior', 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'JSS 3', 'slug' => 'jss3', 'order' => 3, 'category' => 'junior', 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'SS 1',  'slug' => 'ss1',  'order' => 4, 'category' => 'senior', 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'SS 2',  'slug' => 'ss2',  'order' => 5, 'category' => 'senior', 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'SS 3',  'slug' => 'ss3',  'order' => 6, 'category' => 'senior', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 2. Default academic session
        $sessionId = Str::uuid();
        DB::table('academic_sessions')->insert([
            'id'         => $sessionId,
            'name'       => $sessionName,
            'start_date' => "{$currentYear}-09-01",
            'end_date'   => ($currentYear + 1) . '-07-31',
            'is_current' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. Three terms for the session
        DB::table('terms')->insert([
            ['id' => Str::uuid(), 'academic_session_id' => $sessionId, 'name' => 'First Term',  'start_date' => "{$currentYear}-09-01", 'end_date' => "{$currentYear}-12-15", 'is_current' => true,  'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'academic_session_id' => $sessionId, 'name' => 'Second Term', 'start_date' => ($currentYear + 1) . '-01-10', 'end_date' => ($currentYear + 1) . '-04-10', 'is_current' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'academic_session_id' => $sessionId, 'name' => 'Third Term',  'start_date' => ($currentYear + 1) . '-04-28', 'end_date' => ($currentYear + 1) . '-07-31', 'is_current' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 4. Default grading scale
        DB::table('grading_scales')->insert([
            'id'         => Str::uuid(),
            'name'       => 'Default Scale',
            'is_default' => true,
            'grades'     => json_encode([
                ['grade' => 'A', 'min' => 70, 'max' => 100, 'remark' => 'Excellent'],
                ['grade' => 'B', 'min' => 60, 'max' => 69,  'remark' => 'Very Good'],
                ['grade' => 'C', 'min' => 50, 'max' => 59,  'remark' => 'Good'],
                ['grade' => 'D', 'min' => 45, 'max' => 49,  'remark' => 'Pass'],
                ['grade' => 'F', 'min' => 0,  'max' => 44,  'remark' => 'Fail'],
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 5. Default subjects
        DB::table('subjects')->insert([
            ['id' => Str::uuid(), 'name' => 'Mathematics',           'code' => 'MATH',  'category' => 'core',     'department' => 'Sciences',   'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'English Language',      'code' => 'ENG',   'category' => 'core',     'department' => 'General',    'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'Civic Education',       'code' => 'CIV',   'category' => 'core',     'department' => 'General',    'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'Physics',               'code' => 'PHY',   'category' => 'elective', 'department' => 'Sciences',   'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'Chemistry',             'code' => 'CHE',   'category' => 'elective', 'department' => 'Sciences',   'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'Biology',               'code' => 'BIO',   'category' => 'elective', 'department' => 'Sciences',   'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'Government',            'code' => 'GOV',   'category' => 'elective', 'department' => 'Arts',       'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'Economics',             'code' => 'ECO',   'category' => 'elective', 'department' => 'Commercial', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'Literature in English', 'code' => 'LIT',   'category' => 'elective', 'department' => 'Arts',       'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'Geography',             'code' => 'GEO',   'category' => 'elective', 'department' => 'Sciences',   'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'Further Mathematics',   'code' => 'FMATH', 'category' => 'elective', 'department' => 'Sciences',   'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'Computer Studies',      'code' => 'ICT',   'category' => 'elective', 'department' => 'Sciences',   'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'Basic Science',         'code' => 'BSC',   'category' => 'core',     'department' => 'Sciences',   'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'name' => 'Social Studies',        'code' => 'SST',   'category' => 'core',     'department' => 'General',    'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 6. School settings
        DB::table('school_settings')->insert([
            ['id' => Str::uuid(), 'key' => 'ca_weight',                      'value' => '40',                  'type' => 'integer', 'description' => 'Continuous assessment weight (%)',            'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'key' => 'exam_weight',                    'value' => '60',                  'type' => 'integer', 'description' => 'Main exam weight (%)',                        'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'key' => 'default_student_password_mode',  'value' => 'registration_number', 'type' => 'string',  'description' => 'Default password mode for new students',      'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'key' => 'exam_time_limit_default',        'value' => '60',                  'type' => 'integer', 'description' => 'Default exam duration in minutes',            'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'key' => 'shuffle_questions',              'value' => 'true',                'type' => 'boolean', 'description' => 'Shuffle questions by default',                'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'key' => 'allow_result_viewing',           'value' => 'true',                'type' => 'boolean', 'description' => 'Allow students to view results after exam',   'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'key' => 'negative_marking',               'value' => 'false',               'type' => 'boolean', 'description' => 'Enable negative marking',                     'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'key' => 'tab_switch_limit',               'value' => '3',                   'type' => 'integer', 'description' => 'Tab switches before auto-submit',             'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'key' => 'school_name',                    'value' => '',                    'type' => 'string',  'description' => 'Full name of the school',                     'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'key' => 'school_logo',                    'value' => '',                    'type' => 'string',  'description' => 'Path or URL to school logo',                  'created_at' => $now, 'updated_at' => $now],
            ['id' => Str::uuid(), 'key' => 'school_motto',                   'value' => '',                    'type' => 'string',  'description' => 'School motto',                                'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}