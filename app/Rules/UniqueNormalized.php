<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\NormalizeName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueNormalized implements ValidationRule
{
    private array $wheres = [];

    private ?string $ignoreId = null;

    private string $ignoreColumn = 'id';

    private bool $withoutTrashed = false;

    public function __construct(
        private readonly string $table,
        private readonly string $column = 'name',
    ) {}

    public function where(string $column, mixed $value): static
    {
        $this->wheres[] = [$column, $value];

        return $this;
    }

    public function ignore(mixed $id, string $column = 'id'): static
    {
        $this->ignoreId = (string) $id;
        $this->ignoreColumn = $column;

        return $this;
    }

    public function withoutTrashed(): static
    {
        $this->withoutTrashed = true;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = NormalizeName::canonical($value);

        $query = DB::table($this->table)
            ->where($this->column, $normalized);

        foreach ($this->wheres as [$column, $val]) {
            $query->where($column, $val);
        }

        if ($this->ignoreId !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignoreId);
        }

        if ($this->withoutTrashed) {
            $query->whereNull('deleted_at');
        }

        if ($query->exists()) {
            $fail('validation.unique')->translate();
        }
    }
}
