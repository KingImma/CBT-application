<?php

namespace App\Enums;

enum RoleType: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case SchoolAdmin = 'school_admin';
    case Teacher = 'teacher';
    case Student = 'student';
}
