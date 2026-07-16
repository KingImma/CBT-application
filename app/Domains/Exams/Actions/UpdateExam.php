<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions;

use App\Domains\Exams\Data\Input\UpdateExamData;
use App\Domains\Exams\Support\ExamLifecycleRules;
use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

final class UpdateExam
{
    public function __construct() {}

    public function execute(Exam $exam, UpdateExamData $dto): Exam
    {
        return DB::transaction(function () use ($exam, $dto) {
            ExamLifecycleRules::isDraft()($exam);

            $payload = $dto->toArray();
            if (isset($payload['settings']) && is_array($payload['settings'])) {
                foreach ($payload['settings'] as $k => $v) {
                    $payload["settings->{$k}"] = $v;
                }
                unset($payload['settings']);
            }

            $exam->update($payload);

            return $exam->fresh();
        });
    }
}
