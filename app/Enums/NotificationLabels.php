<?php 

declare(strict_types=1);

namespace App\Enums;

enum NotificationLabel: string
{
    case Assessment = 'assessment';

    case Submission = "submission";

    case Exam = "exam";

    case Result = "result";

    case Account = "account";
}

