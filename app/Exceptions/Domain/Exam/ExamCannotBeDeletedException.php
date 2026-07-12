<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Exam;

use App\Exceptions\Domain\BaseDomainException;

class ExamCannotBeDeletedException extends BaseDomainException
{
    protected $message = 'Exam cannot be deleted in its current state.';
}
