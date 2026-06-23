<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Data\Exam\Input\UpdateExamData;
use App\Models\Tenant\Exam;
use DomainException;
use Illuminate\Support\Facade\DB;

class UpdateExam
{
    public function execute(Exam $exam, UpdateExamData $data): Exam
    {
        $this->ensureExamIsUpdatable($exam);

        return DB::transaction(fn () => $this->performUpdate($exam, $data));
    }

    private function ensureExamIsUpdatable(Exam $exam): void
    {
        throw_unless($exam->isDraft(), new DomainException('Only draft exams can be updated'));
    }

    private function performUpdate(Exam $exam, UpdateExamData $data): Exam
    {
        // 1. Convert the DTO to an array inside the Action
        $payload = $data->toArray();

        // 2. Handle the Eloquent JSON column flattening
        if (isset($payload['settings']) && is_array($payload['settings'])) {
            foreach ($payload['settings'] as $key => $value) {
                $payload["settings->{$key}"] = $value;
            }
            unset($payload['settings']);
        }

        // 3. Execute the update
        $exam->update($payload);

        return $exam->fresh();
    }
}
