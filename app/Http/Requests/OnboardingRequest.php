<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Tenant\CreateTenantData;
use Illuminate\Foundation\Http\FormRequest;

class OnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function toData(): CreateTenantData
    {
        return CreateTenantData::from($this->all());
    }
}
