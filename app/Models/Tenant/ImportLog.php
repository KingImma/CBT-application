<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'type',
        'filename',
        'status',
        'total_rows',
        'imported',
        'skipped',
        'updated',
        'errors',
        'meta',
        'created_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'imported' => 'integer',
            'skipped' => 'integer',
            'updated' => 'integer',
            'errors' => 'array',
            'meta' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
