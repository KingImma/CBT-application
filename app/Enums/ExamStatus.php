<?php

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Locked = 'locked';
    case Grading = 'grading';
    case Completed = 'completed';
    case Published = 'published';
}
