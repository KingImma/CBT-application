<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Data\Concerns\HasSchemaValidation;
use App\Data\Student\CreateStudentData;
use App\Models\Tenant\ClassArm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStudentRequest extends FormRequest
{
    use HasSchemaValidation;

    protected function schemaClass(): string
    {
        return CreateStudentData::class;
    }

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();

            if (isset($data["class_level_id"], $data["class_arm_id"])) {
                $arm = ClassArm::find($data["class_arm_id"]);

                if (
                    $arm !== null &&
                    $arm->class_level_id !== $data["class_level_id"]
                ) {
                    $validator
                        ->errors()
                        ->add(
                            "class_arm_id",
                            "The selected class arm does not belong to the selected class level.",
                        );
                }
            }
        });
    }
}
