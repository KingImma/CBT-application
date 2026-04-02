<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusType;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 * @mixin \Stancl\Tenancy\Database\Models\Tenant
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasFactory, HasUuids, HasDomains, SoftDeletes;

    protected $table = "tenants";

    public $incrementing = false;

    protected $guarded = []; // allow all mass assignment

    protected $casts = [
        "subscription_status" => StatusType::class,
        "settings" => "array",
        "is_active" => "boolean",
        "trial_ends_at" => "datetime",
        "subscription_ends_at" => "datetime",
        "onboarding_completed_at" => "datetime",
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'database',
            'domain',
            'email',
            'phone',
            'address',
            'city',
            'state',
            'plan_id',
            'subscription_status',
            'trial_ends_at',
            'subscription_ends_at',
            'settings',
            'is_active',
            'onboarding_completed_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }
}
