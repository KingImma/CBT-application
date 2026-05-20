<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SchoolType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
 * 1. What it is: A Laravel FormRequest class (`CompleteOnboardingRequest`).
 * 2. What it does in a nutshell: Validates incoming frontend data and provides a mapped array that converts frontend `camelCase` keys (like `schoolName` and `fullName`) into the exact `snake_case` structure your action expects.
 * 3. Why this was chosen: It keeps the controller incredibly lean by offloading both validation and data transformation. The action receives a perfectly formatted array every time.
 * 4. Expected deliverables and alternatives: Delivers a clean, safe array via the custom `mappedData()` method. The alternative is doing this transformation directly in the controller, which causes logic bloat.
 */

class OnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schoolName' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'alpha_dash', 'max:63', 'unique:tenants,id', 'unique:tenants,handle'],
            'address' => ['nullable', 'string', 'max:500'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'schoolType' => ['nullable', 'string', Rule::enum(SchoolType::class)],

            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],

            'plan_id' => ['nullable', 'string'],
        ];
    }

    public function mappedData(): array
    {
        $validated = $this->validated();

        $nameParts = explode(' ', $validated['fullName'], 2);

        return [
            'name' => $validated['schoolName'],
            'handle' => $validated['handle'],
            'address' => $validated['address'] ?? null,
            'state' => $validated['state'] ?? null,
            'city' => $validated['city'] ?? null,
            'school_type' => $validated['schoolType'] ?? null,

            'admin_first_name' => $nameParts[0],
            'admin_last_name' => $nameParts[1] ?? '',
            'admin_email' => $validated['email'],
            'admin_password' => $validated['password'],
            'admin_phone' => $validated['phone'],

            'plan_id' => $validated['plan_id'] ?? null,

            'curriculum' => [],
        ];
    }
}
