<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Str;
use App\Models\Tenant;

class StoreTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['nullable', 'email', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'city'    => ['nullable', 'string', 'max:100'],
            'state'   => ['nullable', 'string', 'max:50'],
            'plan_id' => ['nullable', 'uuid', 'exists:subscription_plans,id'],
        ];
    }
    
    /**
     * Validates the uniqueness of the slug after basic validation
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('name')) {
                return;
            }

            $slug = Str::slug($this->input('name'));

            if (empty($slug)) {
                return;
            }

            if (Tenant::where('slug', $slug)->exists()) {
                $validator->errors()->add(
                    'name',
                    "A school with the subdomain '{$slug}' already exists. Please use a different school name."
                );
            }
        });
    }
}
