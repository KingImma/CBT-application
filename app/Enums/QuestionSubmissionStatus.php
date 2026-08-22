<?php

namespace App\Enums;

enum QuestionSubmissionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
