<?php

namespace App\Enums;

enum ExamAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted   = 'submitted';
    case Graded      = 'graded';
    case Timed_out   = 'timed_out';
    case Grading = 'grading';
}
