<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Schemas\Concerns\HasSchemaValidation;
use App\Schemas\Requests\Exam\CreateExamRequestData;
use Illuminate\Foundation\Http\FormRequest;

class StoreExamRequest extends FormRequest
{
    use HasSchemaValidation;

    protected function schemaClass(): string
    {
        return CreateExamRequestData::class;
    }

    public function authorize(): bool
    {
        return true;
    }
}
