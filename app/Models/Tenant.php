<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusType;
use App\Models\Tenant\Concerns\HasBroadcasting;
use App\Models\Tenant\Concerns\HasLifecycle;
use App\Models\Tenant\Concerns\HasValidation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use App\Models\Tenant\Concerns\HasCacheInvalidation;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasBroadcasting, HasCacheInvalidation, HasDatabase, HasDomains, HasFactory, HasLifecycle, HasValidation, SoftDeletes;

    protected $table = 'tenants';

    // ID is the tenant slug (e.g. "kings-college-lagos"), assigned explicitly
    // in CreateTenantAction. HasUuids has been removed — it is not appropriate
    // here because the primary key is not a UUID and must not be auto-generated.
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'slug',
        'handle',
        'database',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'logo',
        'plan_id',
        'subscription_status',
        'trial_ends_at',
        'subscription_ends_at',
        'settings',
        'is_active',
        'onboarding_completed_at',
    ];

    protected $casts = [
        'subscription_status' => StatusType::class,
        'settings' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'settings' => 'array',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'handle',
            'database',
            'email',
            'phone',
            'address',
            'city',
            'state',
            'logo',
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInTrial($query)
    {
        return $query->whereNull('subscription_ends_at')
            ->where('trial_ends_at', '>', now());
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
