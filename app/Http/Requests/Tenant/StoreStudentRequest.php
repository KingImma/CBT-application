<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Data\Concerns\HasSchemaValidation;
use App\Data\Student\CreateStudentData;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    use HasSchemaValidation;

    protected function schemaClass(): string
    {
        return CreateStudentData::class;
    }

    public function authorize(): bool
    {
        return true;
    }
}
