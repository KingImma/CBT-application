<?php

declare(strict_types=1);

namespace App\Models\Tenant\Concerns;

use App\Enums\StatusType;

trait HasValidation
{
    public function canSuspend(): bool
    {
        return $this->subscription_status !== StatusType::Suspended;
    }

    public function canReinstate(): bool
    {
        return $this->subscription_status === StatusType::Suspended;
    }

    public function canDelete(): bool
    {
        return $this->trashed() || $this->subscription_status !== StatusType::Active;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isSuspended(): bool
    {
        return $this->subscription_status === StatusType::Suspended;
    }

    public function isInTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture()
            && $this->subscription_ends_at === null;
    }
}
