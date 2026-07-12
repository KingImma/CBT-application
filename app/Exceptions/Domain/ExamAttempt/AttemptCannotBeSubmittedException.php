<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\ExamAttempt;

use App\Exceptions\Domain\BaseDomainException;

class AttemptCannotBeSubmittedException extends BaseDomainException
{
    protected $message = 'Exam attempt cannot be submitted in its current state.';
}
