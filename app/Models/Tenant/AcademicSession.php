<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicSession extends Model
{
    use HasUuids, HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_current' => 'boolean',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function terms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function currentTerm(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Term::class)->where('is_current', true);
    }
}