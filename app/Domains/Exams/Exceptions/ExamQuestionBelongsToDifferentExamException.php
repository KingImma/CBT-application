<?php

declare(strict_types=1);

namespace App\Domains\Exams\Exceptions;

use App\Shared\Support\ApiResponse;
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
