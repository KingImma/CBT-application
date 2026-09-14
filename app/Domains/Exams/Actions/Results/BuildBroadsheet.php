<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Results;

use App\Domains\Exams\Data\Output\Results\BroadsheetData;
use App\Domains\Exams\Data\Output\Results\BroadsheetMetaData;
use App\Domains\Exams\Data\Output\Results\BroadsheetStudentData;
use App\Domains\Exams\Data\Output\Results\BroadsheetSubjectData;
use App\Domains\Exams\Data\Output\Results\StudentSubjectScoreData;
use App\Domains\Exams\Queries\BroadsheetQuery;
use App\Domains\Exams\Support\ResolveCaComponentTypes;
use Spatie\LaravelData\DataCollection;

final class BuildBroadsheet
{
    public function __construct(
        private BroadsheetQuery $query,
        private ResolveCaComponentTypes $resolveCaTypes,
    ) {
    }

    public function execute(
        string $classLevelId,
        ?string $classArmId,
        string $termId,
        string $academicSessionId,
    ): BroadsheetData {
        $raw = $this->query->execute(
            $classLevelId,
            $classArmId,
            $termId,
            $this->resolveCaTypes->execute(),
        );

        return new BroadsheetData(
            meta: new BroadsheetMetaData($classLevelId, $classArmId, $termId, $academicSessionId),
            subjects: $this->mapSubjects($raw['subjects']),
            students: $this->mapStudents($raw['students']),
        );
    }

    /** @param array<int,object> $rows */
    private function mapSubjects(array $rows): DataCollection
    {
        return BroadsheetSubjectData::collect(array_map(
            fn (object $row) => new BroadsheetSubjectData(
                id: $row->subject_id,
                name: $row->subject_name,
                class_average: round((float) $row->class_average, 2),
            ),
            $rows,
        ), DataCollection::class);
    }

    /** @param array<int,object> $rows */
    private function mapStudents(array $rows): DataCollection
    {
        return BroadsheetStudentData::collect(array_map(
            fn (object $row) => new BroadsheetStudentData(
                student_id: $row->student_id,
                full_name: $row->full_name,
                subjects: $this->mapSubjectScores($row->subjects),
                total_score: round((float) $row->total_score, 2),
                average_score: round((float) $row->average_score, 2),
                position: (int) $row->position,
            ),
            $rows,
        ), DataCollection::class);
    }

    private function mapSubjectScores(string $subjectsJson): DataCollection
    {
        $decoded = json_decode($subjectsJson, true) ?? [];

        return StudentSubjectScoreData::collect(array_map(
            fn (array $s) => new StudentSubjectScoreData(
                subject_id: $s['subject_id'],
                ca: round((float) ($s['ca'] ?? 0), 2),
                exam: round((float) ($s['exam'] ?? 0), 2),
                total: round((float) ($s['total'] ?? 0), 2),
            ),
            $decoded,
        ), DataCollection::class);
    }
}
