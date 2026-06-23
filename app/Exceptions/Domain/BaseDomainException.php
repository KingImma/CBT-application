<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;

abstract class BaseDomainException extends Exception
{
    protected int $httpStatus = 422;

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->httpStatus);
    }
}
