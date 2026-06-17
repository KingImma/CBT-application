<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GradingScale extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'grades',
        'is_default',
    ];

    protected $casts = [
        'grades' => 'array',
        'is_default' => 'boolean',
    ];

    public function setAsDefault(): self
    {
        DB::transaction(function () {
            static::where('is_default', true)->update(['is_default' => false]);
            $this->is_default = true;
        });

        return $this;
    }

    public function canDelete(): bool
    {
        return ! $this->is_default;
    }
}
