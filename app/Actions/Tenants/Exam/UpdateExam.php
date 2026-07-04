<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Actions\Base\UpdateAction;
use App\Data\Exam\Input\UpdateExamData;
use App\Models\Tenant\Exam;

final class UpdateExam
{
    public function __construct(private UpdateAction $action) {}

    public function execute(Exam $exam, UpdateExamData $dto): Exam
    {
        return $this->action->execute(
            $exam,
            ['dto' => $dto],
            guard: ExamGuards::isDraft(),
            prepare: function (Exam $e, array $d) {
                $payload = $d['dto']->toArray();

                // flatten nested settings for partial JSON-column update
                if (isset($payload['settings']) && is_array($payload['settings'])) {
                    foreach ($payload['settings'] as $k => $v) {
                        $payload["settings->{$k}"] = $v;
                    }
                    unset($payload['settings']);
                }

                return $payload;
            },
        );
    }
}
