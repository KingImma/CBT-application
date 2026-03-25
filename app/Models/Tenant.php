<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, SoftDeletes;

    protected $guarded = []; // allow all mass assignment

    protected $casts = [
        'settings'                => 'array',
        'is_active'               => 'boolean',
        'trial_ends_at'           => 'datetime',
        'subscription_ends_at'    => 'datetime',
        'onboarding_completed_at' => 'datetime',
    ];
}