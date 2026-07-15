<?php

declare(strict_types=1);

namespace App\Models\Tenant\User\Concerns;

use App\Enums\RoleType;
use App\Domains\Tenancy\Events\UserActivated;
use App\Domains\Tenancy\Events\UserDeactivated;
use Illuminate\Database\Eloquent\Builder;

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

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => $query,
        };
    }

    public function scopeSearch(Builder $query, string $search, array $searchFields = ['first_name', 'last_name', 'email']): Builder
    {
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search, $searchFields) {
            foreach ($searchFields as $field) {
                $q->orWhere($field, 'ilike', "%{$search}%");
            }
        });
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

    public static function bootHasLifecycle(): void
    {
        static::updated(function ($user) {
            // Only fire if the 'is_active' status actually flipped during this update
            if ($user->wasChanged('is_active')) {
                if ($user->is_active) {
                    UserActivated::dispatch($user);
                } else {
                    // Instantly revoke access
                    $user->tokens()->delete(); 
                    UserDeactivated::dispatch($user);
                }
            }
        });
    }
}
