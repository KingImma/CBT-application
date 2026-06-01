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

    /**
     * Build a response data array suitable for API responses.
     *
     * @return array<string, mixed>
     */
    public function toResponseData(bool $dryRun): array
    {
        if ($dryRun) {
            $data = [
                'dry_run' => true,
                'total_rows' => $this->totalRows,
                'can_proceed' => true,
            ];

            if ($this->duplicates !== []) {
                $data['duplicates'] = $this->duplicates;
            }

            return $data;
        }

        $data = [
            'imported' => $this->imported,
        ];

        if ($this->skipped > 0) {
            $data['skipped'] = $this->skipped;
        }

        if ($this->updated > 0) {
            $data['updated'] = $this->updated;
        }

        return $data;
    }
}
