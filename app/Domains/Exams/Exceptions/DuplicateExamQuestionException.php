<?php

declare(strict_types=1);

namespace App\Domains\Exams\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class DuplicateExamQuestionException extends BaseDomainException
{
    protected $message = 'This question has already been added to the exam.';
}
