<?php

declare(strict_types=1);

namespace App\Exceptions\Business;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;

class ExamQuestionNotFoundException extends Exception
{
    public function __construct()
    {
        parent::__construct('Question not found in this exam.');
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 404);
    }
}
