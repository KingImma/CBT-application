<?php

declare(strict_types=1);

namespace App\Models\Tenant\Concerns;

use App\Enums\StatusType;
use App\Exceptions\Domain\Tenant\TenantAlreadyActiveException;
use App\Exceptions\Domain\Tenant\TenantAlreadySuspendedException;
use Illuminate\Support\Facades\DB;

trait HasLifecycle
{
    public function suspend(): self
    {
        throw_unless($this->canSuspend(), TenantAlreadySuspendedException::class);

        DB::transaction(function () {
            $this->subscription_status = StatusType::Suspended;
            $this->is_active = false;
        });

        return $this;
    }

    public function reinstate(): self
    {
        throw_unless($this->canReinstate(), TenantAlreadyActiveException::class);

        DB::transaction(function () {
            $this->subscription_status = StatusType::Active;
            $this->is_active = true;
        });

        return $this;
    }

    public function activate(): self
    {
        DB::transaction(function () {
            $this->is_active = true;
            $this->subscription_status = StatusType::Active;
            $this->onboarding_completed_at = now();
        });

        return $this;
    }

    public function expireTrial(): self
    {
        $this->is_active = false;
        $this->subscription_status = StatusType::Expired;

        return $this;
    }
}
