<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domains\Tenancy\Support\HasSchemaValidation;
use App\Domains\Exams\Data\Input\CreateExamData;
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
