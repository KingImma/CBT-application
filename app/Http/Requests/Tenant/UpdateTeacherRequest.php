<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Domains\Tenancy\Support\HasSchemaValidation;
use App\Domains\Teachers\Data\UpdateTeacherData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeacherRequest extends FormRequest
{
    use HasSchemaValidation;

    protected function schemaClass(): string
    {
        return UpdateTeacherData::class;
    }

    public function authorize(): bool
    {
        return true;
    }
}
