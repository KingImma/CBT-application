<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Tenants\Exam\PublishExamAction;
use App\Models\Tenant\Exam;

class ExamPublishingService
{
    public function __construct(
        private PublishExamAction $publishAction,
    ) {}

    public function publish(Exam $exam): Exam
    {
        return $this->publishAction->execute($exam);
    }
}
