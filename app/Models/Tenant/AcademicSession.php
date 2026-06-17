<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Exceptions\Domain\Session\SessionAlreadyCurrentException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class AcademicSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'is_current',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function currentTerm(): HasOne
    {
        return $this->hasOne(Term::class)->where('is_current', true);
    }

    public function setAsCurrent(): self
    {
        throw_unless(! $this->is_current, SessionAlreadyCurrentException::class);

        DB::transaction(function () {
            static::where('is_current', true)->update(['is_current' => false]);
            Term::where('is_current', true)->update(['is_current' => false]);
            $this->is_current = true;
        });

        return $this;
    }
}
