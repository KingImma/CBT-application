<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Data\Concerns\HasSchemaValidation;
use App\Data\Exam\Input\CreateExamData;
use Illuminate\Foundation\Http\FormRequest;

class StoreExamRequest extends FormRequest
{
    use HasSchemaValidation;

    protected function schemaClass(): string
    {
        return CreateExamData::class;
    }

    public function authorize(): bool
    {
        return $this->user()?->hasRole('school_admin') ?? false;
    }
}
