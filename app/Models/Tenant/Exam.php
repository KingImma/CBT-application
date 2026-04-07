<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exam extends Model
{
    use HasUuids, HasFactory, SoftDeletes; // Added SoftDeletes as per document 

    protected $guarded = [];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end'   => 'datetime',
        'settings'        => 'array', // Automatically casts JSONB to PHP array 
        'duration_minutes'=> 'integer',
        'total_marks'     => 'decimal:2',
        'pass_mark'       => 'decimal:2',
        'max_attempts'    => 'integer',
    ];
    /**
     * @return BelongsTo<Term,Exam>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
    /**
     * @return BelongsTo<Subject,Exam>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
    /**
     * @return BelongsTo<ClassLevel,Exam>
     */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }
    /**
     * @return BelongsTo<ClassArm,Exam>
     */
    public function classArm(): BelongsTo
    {
        return $this->belongsTo(ClassArm::class);
    }
    /**
     * @return BelongsTo<User,Exam>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}