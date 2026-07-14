<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domains\Tenancy\Support\HasSchemaValidation;
use App\Domains\Students\Data\UpdateStudentData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    use HasSchemaValidation;

    protected function schemaClass(): string
    {
        return UpdateStudentData::class;
    }

    public function authorize(): bool
    {
        return true;
    }
}
