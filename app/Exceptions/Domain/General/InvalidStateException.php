<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\General;

use App\Exceptions\Domain\BaseDomainException;

class InvalidStateException extends BaseDomainException
{
    protected $message = 'Invalid state.';
}