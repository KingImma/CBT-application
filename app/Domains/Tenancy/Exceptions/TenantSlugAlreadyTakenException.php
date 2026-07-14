<?php

namespace App\Domains\Tenancy\Exceptions;

use App\Shared\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;

class TenantSlugAlreadyTakenException extends Exception
{
    public function __construct(private readonly string $slug)
    {
        parent::__construct("the subdomain {$slug} is already taken");
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            "The subdomain '{$this->slug}' is already taken. Please choose a different school name.",
            409,
            meta: ['slug' => $this->slug]
        );
    }
}
