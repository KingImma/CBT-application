<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Exception;
use Illuminate\Http\JsonResponse;
use App\Support\ApiResponse;

abstract class BaseDomainException extends Exception
{
    protected int $httpStatus = 422;

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->httpStatus);
    }
}