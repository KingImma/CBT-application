<?php

declare(strict_types=1);

namespace App\Domains\Settings\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class GradingScaleCannotBeDeletedException extends BaseDomainException
{
    protected $message = 'Grading scale cannot be deleted in its current state.';
}
