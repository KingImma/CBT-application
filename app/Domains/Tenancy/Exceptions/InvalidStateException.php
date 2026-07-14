<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Exceptions;



class InvalidStateException extends BaseDomainException
{
    protected $message = 'Invalid state.';
}
