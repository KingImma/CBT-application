<?php

declare(strict_types=1);

namespace App\Models\Tenant\Concerns;

use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Term;
use App\Services\SessionTermContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToSessionTerm
{
    public static function bootBelongsToSessionTerm(): void
    {
        static::creating(function ($model) {
            $context = app(SessionTermContext::class);

            if (! $model->academic_session_id) {
                $model->academic_session_id = $context->currentSessionId();
            }

            if (! $model->term_id) {
                $model->term_id = $context->currentTermId();
            }
        });
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function scopeCurrentTerm(Builder $query): Builder
    {
        $context = app(SessionTermContext::class);

        return $query
            ->where('academic_session_id', $context->currentSessionId())
            ->where('term_id', $context->currentTermId());
    }

    public function scopeForTerm(Builder $query, string $sessionId, string $termId): Builder
    {
        return $query
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId);
    }
}
