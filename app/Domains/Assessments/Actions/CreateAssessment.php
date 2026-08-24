<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Assessments\Data\Input\CreateAssessmentData;
use App\Models\Tenant\Assessment;
use Illuminate\Support\Facades\DB;

final class CreateAssessment
{
    public function __construct() {}

    /**
     * Create the stable, school-wide assessment definition. No class binding,
     * no term, windows or status — occurrences live on AssessmentSchedule
     * rows.
     */
    public function execute(CreateAssessmentData $dto, string $createdBy): Assessment
    {
        return DB::transaction(fn (): Assessment => Assessment::create([
            'title' => $dto->title,
            'created_by' => $createdBy,
            'total_marks' => $dto->total_marks,
            'duration_minutes' => $dto->duration_minutes,
            'description' => $dto->description,
        ]));
    }
}
