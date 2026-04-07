<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradingScale extends Model
{
    use HasUuids, HasFactory;

    protected $guarded = [];

    protected $casts = [
        'grades'     => 'array',
        'is_default' => 'boolean',
    ];
}