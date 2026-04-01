<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SubscriptionPlan extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    
    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];
}
