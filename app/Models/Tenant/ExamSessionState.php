<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class ExamSessionState extends Model
{
    protected $fillable = [
        'tenant_id',
        'attempt_id',
        'time_remaining_seconds',
        'connection_alive',
        'last_activity_at',
    ];

    protected $casts = [
        'connection_alive' => 'boolean',
        'time_remaining_seconds' => 'integer',
        'last_activity_at' => 'datetime',
    ];
}
