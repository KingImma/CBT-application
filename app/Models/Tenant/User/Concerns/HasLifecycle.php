<?php

declare(strict_types=1);

namespace App\Models\Tenant\User\Concerns;

use App\Enums\RoleType;

trait HasLifecycle
{
    public function activate(): self
    {
        $this->is_active = true;

        return $this;
    }

    public function deactivate(): self
    {
        $this->is_active = false;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isStudent(): bool
    {
        return $this->isRole(RoleType::Student->value);
    }

    public function isTeacher(): bool
    {
        return $this->isRole(RoleType::Teacher->value);
    }

    public function isSuperAdmin(): bool
    {
        return $this->isRole(RoleType::SuperAdmin->value);
    }
}
