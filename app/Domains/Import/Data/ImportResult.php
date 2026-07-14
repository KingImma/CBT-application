<?php

declare(strict_types=1);

namespace App\Domains\Import\Data;

class ImportResult
{
    public function __construct(
        private readonly bool $success,
        private readonly ?string $message = null,
        private readonly ?int $totalRows = null,
        private readonly ?int $imported = null,
        private readonly ?int $skipped = null,
        private readonly ?int $updated = null,
        private readonly array $errors = [],
        private readonly array $duplicates = [],
        private readonly array $missingHeaders = [],
        private readonly bool $canProceed = true,
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getTotalRows(): ?int
    {
        return $this->totalRows;
    }

    public function getImported(): ?int
    {
        return $this->imported;
    }

    public function getSkipped(): ?int
    {
        return $this->skipped;
    }

    public function getUpdated(): ?int
    {
        return $this->updated;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getDuplicates(): array
    {
        return $this->duplicates;
    }

    public function getMissingHeaders(): array
    {
        return $this->missingHeaders;
    }

    public function canProceed(): bool
    {
        return $this->canProceed;
    }

    public function hasBlockingErrors(): bool
    {
        return $this->getMissingHeaders() !== [] || $this->getErrors() !== [];
    }

    public function toResponseData(bool $dryRun): array
    {
        if ($dryRun) {
            $data = [
                'dry_run' => true,
                'total_rows' => $this->getTotalRows(),
                'can_proceed' => true,
            ];

            if ($this->getDuplicates() !== []) {
                $data['duplicates'] = $this->getDuplicates();
            }

            return $data;
        }

        $data = [
            'imported' => $this->getImported(),
        ];

        if ($this->getSkipped() > 0) {
            $data['skipped'] = $this->getSkipped();
        }

        if ($this->getUpdated() > 0) {
            $data['updated'] = $this->getUpdated();
        }

        return $data;
    }
}
