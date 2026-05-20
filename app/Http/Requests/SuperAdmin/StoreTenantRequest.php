<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // School details
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:50'],
            'plan_id' => ['nullable', 'uuid', 'exists:subscription_plans,id'],

            // Initial school admin credentials.
            // These are stored temporarily in tenant settings and consumed by
            // TenantDatabaseSeeder when the tenant database is provisioned.
            'admin_first_name' => ['required', 'string', 'max:100'],
            'admin_last_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'admin_first_name.required' => 'The school admin first name is required.',
            'admin_last_name.required' => 'The school admin last name is required.',
            'admin_email.required' => 'The school admin email address is required.',
            'admin_email.email' => 'Please provide a valid admin email address.',
            'admin_password.required' => 'The school admin password is required.',
            'admin_password.min' => 'The admin password must be at least 8 characters.',
        ];
    }
}
