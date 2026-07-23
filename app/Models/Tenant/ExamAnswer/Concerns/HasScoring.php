<?php

declare(strict_types=1);

namespace App\Models\Tenant\ExamAnswer\Concerns;

trait HasScoring
{
    public function flag(): self
    {
        $this->is_flagged = true;

        return $this;
    }

    public function unflag(): self
    {
        $this->is_flagged = false;

        return $this;
    }

    public function toggleFlag(): self
    {
        $this->is_flagged = ! $this->is_flagged;

        return $this;
    }
}
