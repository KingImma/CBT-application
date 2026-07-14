<?php

declare(strict_types=1);

namespace App\Domains\Import\Data;

class BulkOperationResult
{
    public function __construct(
        private readonly int $succeeded,
        private readonly int $failed,
        private readonly array $failures = [],
        private readonly ?string $message = null,
    ) {}

    public static function fromLoop(int $succeeded, int $failed, array $failures = []): self
    {
        $parts = [];
        if ($succeeded > 0) {
            $parts[] = "{$succeeded} succeeded";
        }
        if ($failed > 0) {
            $parts[] = "{$failed} failed";
        }

        return new self(
            succeeded: $succeeded,
            failed: $failed,
            failures: $failures,
            message: implode(', ', $parts).'.',
        );
    }

    public function getSucceeded(): int
    {
        return $this->succeeded;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function getFailures(): array
    {
        return $this->failures;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function hasFailures(): bool
    {
        return $this->getFailed() > 0;
    }

    public function isFullSuccess(): bool
    {
        return $this->getFailed() === 0;
    }

    public function toArray(): array
    {
        $data = [
            'succeeded' => $this->getSucceeded(),
            'failed' => $this->getFailed(),
        ];

        if ($this->getFailures() !== []) {
            $data['failures'] = $this->getFailures();
        }

        return $data;
    }
}
