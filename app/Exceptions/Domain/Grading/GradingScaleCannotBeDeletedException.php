<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Grading;

use App\Exceptions\Domain\BaseDomainException;

class GradingScaleCannotBeDeletedException extends BaseDomainException
{
    protected $message = 'Grading scale cannot be deleted in its current state.';
}
