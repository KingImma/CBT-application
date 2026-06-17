<?php

declare(strict_types=1);

namespace App\Models\Tenant\ExamAnswer\Concerns;

trait HasScoring
{
    public function markCorrect(float $marks): self
    {
        $this->is_correct = true;
        $this->marks_awarded = $marks;

        return $this;
    }

    public function markIncorrect(): self
    {
        $this->is_correct = false;
        $this->marks_awarded = 0.0;

        return $this;
    }

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
