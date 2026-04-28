<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Str;

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
            "name" => ["required", "string", "max:255"],
            "email" => ["nullable", "email", "max:255"],
            "phone" => ["nullable", "string", "max:20"],
            "address" => ["nullable", "string"],
            "city" => ["nullable", "string", "max:100"],
            "state" => ["nullable", "string", "max:50"],
            "plan_id" => ["nullable", "uuid", "exists:subscription_plans,id"],

            // Initial school admin credentials.
            // These are stored temporarily in tenant settings and consumed by
            // TenantDatabaseSeeder when the tenant database is provisioned.
            "admin_first_name" => ["required", "string", "max:100"],
            "admin_last_name" => ["required", "string", "max:100"],
            "admin_email" => ["required", "email", "max:255"],
            "admin_password" => ["required", "string", "min:8"],
        ];
    }

    public function messages(): array
    {
        return [
            "admin_first_name.required" =>
                "The school admin first name is required.",
            "admin_last_name.required" =>
                "The school admin last name is required.",
            "admin_email.required" =>
                "The school admin email address is required.",
            "admin_email.email" =>
                "Please provide a valid admin email address.",
            "admin_password.required" =>
                "The school admin password is required.",
            "admin_password.min" =>
                "The admin password must be at least 8 characters.",
        ];
    }

    /**
     * Validate slug uniqueness after basic rules pass.
     * Runs the same slug-building logic as CreateTenantAction so the
     * error is raised before the action even runs.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->filled("name")) {
                return;
            }

            $slug = Str::slug(mb_strtolower($this->input("name")));

            if (empty($slug)) {
                $validator
                    ->errors()
                    ->add(
                        "name",
                        "The school name could not be converted into a valid subdomain. Please use standard characters.",
                    );
                return;
            }

            if (Tenant::where("slug", $slug)->exists()) {
                $validator
                    ->errors()
                    ->add(
                        "name",
                        "A school with the subdomain '{$slug}' already exists. Please use a different school name.",
                    );
            }
        });
    }
}
