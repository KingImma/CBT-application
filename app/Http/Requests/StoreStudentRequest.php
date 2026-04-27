<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'          => ['required', 'string', 'max:100'],
            'last_name'           => ['required', 'string', 'max:100'],
            'email'               => ['nullable', 'email', 'unique:users,email'],
            'class_level_id'      => ['required', 'uuid', 'exists:class_levels,id'],
            'class_arm_id'        => ['nullable', 'uuid', 'exists:class_arms,id'],
            'admission_number'    => ['nullable', 'string', 'max:50', 'unique:student_profiles,admission_number'],
            'date_of_birth'       => ['nullable', 'date'],
            'gender'              => ['nullable', 'in:male,female,other'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'          => 'The first name is required.',
            'last_name.required'           => 'The last name is required.',
            'email.email'                  => 'Please provide a valid email address.',
            'class_level_id.exists'        => 'The selected class level does not exist.',
            'class_arm_id.exists'          => 'The selected class arm does not exist.',
            'admission_number.unique'      => 'This admission number is already in use.',
            'date_of_birth.date'           => 'Please provide a valid date of birth.',
            'gender.in'                    => 'Please select a valid gender.',
        ];
    }
}
