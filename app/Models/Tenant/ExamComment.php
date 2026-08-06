<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamComment extends Model
{
    use HasUuids;

    protected $fillable = [
        'exam_id',
        'author_id',
        'parent_id',
        'comment',
    ];

  public function exam(): BelongsTo
  {
    return $this->belongsTo(Exam::class);
  }

  public function author(): BelongsTo
  {
    return $this->belongsTo(User::class, 'author_id');
  }

  public function parent(): BelongsTo
  {
    return $this->belongsTo(ExamComment::class, 'parent_id');
  }

  public function replies(): HasMany
  {
    return $this->hasMany(ExamComment::class, 'parent_id');
  }
}
