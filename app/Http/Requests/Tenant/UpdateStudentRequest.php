<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
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
        $userId = $this->route('id');
        
        return [
            'first_name'    => ['sometimes', 'string', 'max:100'],
            'last_name'     => ['sometimes', 'string', 'max:100'],
            'email'         => ['sometimes', 'nullable', 'email', 'unique:users,email,' . $userId],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:20'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender'        => ['sometimes', 'nullable', 'in:male,female,other'],
            'guardian_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'admission_number'     => ['sometimes', 'string', 'max:50', 'unique:student_profiles,admission_number,' . $userId . ',id'],
        ];
    }
}
