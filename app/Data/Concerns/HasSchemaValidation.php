<?php

declare(strict_types=1);

namespace App\Data\Concerns;

use Spatie\LaravelData\Data;

trait HasSchemaValidation
{
    abstract protected function schemaClass(): string;

    public function rules(): array
    {
        $schemaClass = $this->schemaClass();

        return $schemaClass::getValidationRules($this->all());
    }

    public function validatedData(): Data
    {
        $schemaClass = $this->schemaClass();

        return $schemaClass::from($this->validated());
    }
}
