<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Exam;

use App\Exceptions\Domain\BaseDomainException;

class ExamStateTransitionException extends BaseDomainException
{
    protected $message = 'Invalid exam state transition.';
}
