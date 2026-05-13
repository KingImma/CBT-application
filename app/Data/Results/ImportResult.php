<?php

declare(strict_types=1);

namespace App\Data\Results;

class ImportResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = null,
        public readonly ?int $totalRows = null,
        public readonly ?int $imported = null,
        public readonly ?int $skipped = null,
        public readonly ?int $updated = null,
        public readonly array $errors = [],
        public readonly array $duplicates = [],
        public readonly array $missingHeaders = [],
        public readonly bool $canProceed = true,
    ) {}

    public function hasBlockingErrors(): bool
    {
        return $this->missingHeaders !== [] || $this->errors !== [];
    }
}
