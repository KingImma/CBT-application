<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Data\Concerns\HasSchemaValidation;
use App\Data\Teacher\CreateTeacherData;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherRequest extends FormRequest
{
    use HasSchemaValidation;

    protected function schemaClass(): string
    {
        return CreateTeacherData::class;
    }

    public function authorize(): bool
    {
        return true;
    }
}
