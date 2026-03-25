<?php

namespace App\Enums;

enum ExamAttendanceStatus: string
{
    case Present = "present";
    case Absent = "Absent";
    case Absent_With_Permission = "absent_with_permission";
}
