<?php

namespace App\Enums;

enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case SubmissionsClosed = 'submissions_closed';
    case Active = 'active';
    case Completed = 'completed';
}
