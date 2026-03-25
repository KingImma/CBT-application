<?php

namespace App\Enums;

enum ExamStatus: string
{
    case Draft     = 'draft';
    case Scheduled = 'scheduled';
    case Active    = 'active';
    case Grading   = 'grading';
    case Completed = 'completed';
    case Published = 'published';
}
