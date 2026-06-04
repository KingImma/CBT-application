<?php

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Active = 'active';
    case Completed = 'completed';

    // Reserved for future use
    // case Scheduled = 'scheduled';
}
