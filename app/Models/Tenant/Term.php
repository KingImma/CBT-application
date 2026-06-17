<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Exceptions\Domain\Session\TermAlreadyCurrentException;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Term extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'is_current',
        'start_date',
        'end_date',
        'academic_session_id',
        'tenant_id',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'tenant_id' => 'string',
    ];

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function setAsCurrent(): self
    {
        throw_unless($this->academicSession->is_current, \InvalidArgumentException::class, 'Academic session must be current before setting a current term.');
        throw_unless(! $this->is_current, TermAlreadyCurrentException::class);

        DB::transaction(function () {
            static::where('is_current', true)->update(['is_current' => false]);
            $this->is_current = true;
        });

        return $this;
    }
}
