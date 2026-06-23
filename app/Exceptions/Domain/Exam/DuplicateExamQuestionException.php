<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Exam;

use App\Exceptions\Domain\BaseDomainException;

class DuplicateExamQuestionException extends BaseDomainException
{
    protected string $message = 'This question has already been added to the exam.';
}
