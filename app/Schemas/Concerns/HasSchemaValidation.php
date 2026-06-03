<?php

declare(strict_types=1);

namespace App\Schemas\Concerns;

use Spatie\LaravelData\Data;

/**
 * Bridge between Spatie Data objects and FormRequest validation.
 *
 * Use this trait on a FormRequest to drive validation rules from a Data
 * object's attributes. The Data object becomes the single source of truth
 * for both validation rules and typed request data.
 *
 * ```php
 * class StoreExamRequest extends FormRequest
 * {
 *     use HasSchemaValidation;
 *
 *     protected function schemaClass(): string
 *     {
 *         return CreateExamRequestData::class;
 *     }
 * }
 * ```
 */
trait HasSchemaValidation
{
    /**
     * The fully-qualified class name of the Spatie Data object that
     * carries the validation attributes for this request.
     */
    abstract protected function schemaClass(): string;

    /**
     * Resolve validation rules from the Data object.
     *
     * Spatie Data's `getValidationRules()` reads the PHP attributes
     * (e.g. #[Required], #[StringType], #[Exists]) declared on the
     * Data object's constructor properties and returns the Laravel
     * validation rules array.
     */
    public function rules(): array
    {
        $schemaClass = $this->schemaClass();

        return $schemaClass::getValidationRules($this->all());
    }

    /**
     * Hydrate a validated Data object from the request.
     *
     * Use this in the controller to get a fully typed DTO backed by the
     * same validation rules that were just run.
     */
    public function validatedData(): Data
    {
        $schemaClass = $this->schemaClass();

        return $schemaClass::from($this->validated());
    }
}
