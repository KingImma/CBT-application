<?php

namespace App\Enums;

enum RoleType: string
{
    case SchoolAdmin = 'school_admin';
    case Teacher = 'teacher';
    case Student = 'student';
}
