<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ClassArmSubjectPivot extends Pivot
{
    use HasUuids;

    protected $table = 'class_arm_subject';
    public    $incrementing = false;
    protected $keyType      = 'string';

    protected $casts = [
        'is_compulsory' => 'boolean',
    ];
}