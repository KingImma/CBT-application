<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use Exception;

class AccountDeactivatedException extends Exception
{
    public function __construct()
    {
        parent::__construct('Your account has been deactivated.');
    }
}
