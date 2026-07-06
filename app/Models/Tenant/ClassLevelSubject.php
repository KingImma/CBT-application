<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ClassLevelSubject extends Pivot
{
    use HasUuids;

    protected $table = 'class_level_subject';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'class_level_id',
        'subject_id',
        'is_compulsory',
    ];

    protected $casts = [
        'is_compulsory' => 'boolean',
    ];
}
