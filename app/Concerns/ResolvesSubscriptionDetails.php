<?php

namespace App\Concerns;

use App\Enums\StatusType;
use App\Models\SubscriptionPlan;

trait ResolvesSubscriptionDetails
{
    private const TRIAL_DAYS = 30;

    /**
     * @return array<string,mixed>
     */
    protected function resolveSubscriptionDetails(?string $planId): array
    {
        if (empty($planId)) {
            return [
                'subscription_status' => StatusType::Trial->value,
                'trial_ends_at' => now()->addDays(self::TRIAL_DAYS),
                'subscription_ends_at' => null,
            ];
        }

        $plan = SubscriptionPlan::find($planId);
        $endsAt = $plan?->interval === 'yearly' ? now()->addYear() : now()->addMonth();

        return [
            'subscription_status' => StatusType::Active->value,
            'trial_ends_at' => null,
            'subscription_ends_at' => $endsAt,
        ];
    }

    protected function resolveCentralDomain(): string
    {
        return config('app.central_domain')
            ?? collect(config('tenancy.central_domains', []))
                ->reject(fn ($d) => in_array($d, ['127.0.0.1', 'localhost'], true))
                ->first()
            ?? 'localhost';
    }
}
