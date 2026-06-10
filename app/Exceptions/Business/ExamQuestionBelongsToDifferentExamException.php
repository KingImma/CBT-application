<?php

declare(strict_types=1);

namespace App\Exceptions\Business;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;

class ExamQuestionBelongsToDifferentExamException extends Exception
{
    public function __construct()
    {
        parent::__construct('Question belongs to a different exam.');
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), 422);
    }
}
