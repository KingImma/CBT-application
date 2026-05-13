<?php

declare(strict_types=1);

namespace App\Data\Results;

class BulkOperationResult
{
    public function __construct(
        public readonly int   $succeeded,
        public readonly int   $failed,
        public readonly array $failures = [],
        public readonly ?string $message = null,
    ) {}

    public static function fromLoop(int $succeeded, int $failed, array $failures = []): self
    {
        $parts = [];
        if ($succeeded > 0) $parts[] = "{$succeeded} succeeded";
        if ($failed > 0)    $parts[] = "{$failed} failed";

        return new self(
            succeeded: $succeeded,
            failed:    $failed,
            failures:  $failures,
            message:   implode(', ', $parts) . '.',
        );
    }

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }

    public function isFullSuccess(): bool
    {
        return $this->failed === 0;
    }

    public function toArray(): array
    {
        $data = [
            'succeeded' => $this->succeeded,
            'failed'    => $this->failed,
        ];

        if ($this->failures !== []) {
            $data['failures'] = $this->failures;
        }

        return $data;
    }
}
