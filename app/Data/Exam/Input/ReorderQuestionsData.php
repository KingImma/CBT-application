<?php

declare(strict_types=1);

namespace App\Data\Exam\Input;

use Spatie\LaravelData\Data;

class ReorderQuestionsData extends Data
{
    public function __construct(
        /**
         * Expects a key-value mapping of Question UUIDs to their new order index.
         * @example {"9a1b2c3d-4e5f...": 1, "9a1b2c3d-5f6g...": 2}
         * * @var array<string, int> 
         */
        public readonly array $order,
    ) {}

    /**
     * Define custom Laravel validation rules for the nested array values.
     */
    public static function rules(): array
    {
        return [
            'order'   => ['required', 'array'],
            'order.*' => ['required', 'integer', 'min:1'],
        ];
    }
}
