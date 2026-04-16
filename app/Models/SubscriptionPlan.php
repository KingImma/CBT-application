<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasUuids, HasFactory;

    protected $guarded = [];

    protected $casts = [
        "features" => "array",
        "is_active" => "boolean",
        "price_monthly" => "decimal:2",
        "price_yearly" => "decimal:2",
        "max_students" => "integer",
        "max_teachers" => "integer",
        "max_exams_per_term" => "integer",
    ];

    /**
     * @return HasMany<Tenant, SubscriptionPlan>
     */
    public function tenants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tenant::class, 'plan_id');   
    }
    /**
     * @param Builder<Model> $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where("is_active", true);
    }
}
