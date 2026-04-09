<?php

namespace App\Actions\Tenants\Student;

use App\Models\Tenant\User;
use App\Models\Tenant\StudentProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


class UpdateStudentAction
{
    public function handle($student, $data)
    {
        $student->update($data);    
        
        return $student->load(['user', 'classLevel', 'classArm']);
    }
}
