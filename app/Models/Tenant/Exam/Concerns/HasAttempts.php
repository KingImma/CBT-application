<?php

declare(strict_types=1);

namespace App\Models\Tenant\Exam\Concerns;

use App\Enums\RoleType;
use App\Models\Tenant\User;

trait HasAttempts
{
    public function expectedAttempts(): int
    {
        return User::role(RoleType::Student->value)
            ->where("is_active", true)
            ->whereHas("studentProfile", function ($q) {
                $q->where("class_level_id", $this->class_level_id);
                if ($this->class_arm_id) {
                    $q->where("class_arm_id", $this->class_arm_id);
                }
            })
            ->count();
    }

    public function actualAttempts(): int
    {
        return $this->attempts()->completed()->count();
    }

    public function completionRate(): float
    {
        $expected = $this->expectedAttempts();

        return $expected > 0
            ? round(($this->actualAttempts() / $expected) * 100, 2)
            : 0.0;
    }
}
