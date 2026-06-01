<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use Exception;

class InvalidCredentialsException extends Exception
{
    public function __construct()
    {
        parent::__construct('Invalid email or password.');
    }
}
