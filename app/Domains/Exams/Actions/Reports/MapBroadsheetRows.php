<?php

declare(strict_types=1);

namespace App\Domains\Results\Actions\Reports;

use App\Domains\Results\Data\Output\BroadsheetRowData;
use App\Domains\Results\Data\Output\BroadsheetScoreCellData;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\Subject;
use App\Models\Tenant\TermResult;
use Illuminate\Support\Collection;

final class MapBroadsheetRows
{
    /**
     * @param  array<string, int>  $scopeRanks
     * @param  array<string, int>  $levelRanks
     * @return array<BroadsheetRowData>
     */
    public function execute(
        object $data,
        array $scopeRanks,
        array $levelRanks,
    ): array {
        return $data->students
            ->map(
                function (StudentProfile $profile) use (
                    $data,
                    $scopeRanks,
                    $levelRanks,
                ): BroadsheetRowData {
                    $studentId = (string) $profile->user_id;

                    /** @var Collection<int, TermResult> $studentResults */
                    $studentResults = $data->resultsByStudentId
                        ->get($studentId, collect());

                    $resultsBySubjectId = $studentResults->keyBy('subject_id');

                    $scores = $data->subjects
                        ->mapWithKeys(
                            function (Subject $subject) use (
                                $resultsBySubjectId,
                            ): array {
                                $result = $resultsBySubjectId->get($subject->id);

                                return [
                                    (string) $subject->id => $this->makeCell(
                                        $subject,
                                        $result,
                                    ),
                                ];
                            }
                        )
                        ->all();

                    $hasTotal = array_key_exists(
                        $studentId,
                        $scopeRanks,
                    );

                    $total = $hasTotal
                        ? round(
                            (float) $studentResults->sum(
                                fn (TermResult $result): float => (float) $result->total_score
                            ),
                            2,
                        )
                        : null;

                    $subjectCount = $data->subjects->count();

                    $average = (
                        $total !== null &&
                        $subjectCount > 0
                    )
                        ? round($total / $subjectCount, 2)
                        : null;

                    $firstName = (string) (
                        $profile->user?->first_name ?? ''
                    );

                    $lastName = (string) (
                        $profile->user?->last_name ?? ''
                    );

                    return new BroadsheetRowData(
                        student_id: $studentId,
                        student_name: trim(
                            $firstName.' '.$lastName
                        ),
                        admission_number: $profile->admission_number,
                        scores: $scores,
                        total: $total,
                        average: $average,
                        position: $scopeRanks[$studentId] ?? null,
                        position_in_level: $levelRanks[$studentId] ?? null,
                    );
                }
            )
            ->values()
            ->all();
    }

    private function makeCell(
        Subject $subject,
        ?TermResult $result,
    ): BroadsheetScoreCellData {
        return new BroadsheetScoreCellData(
            subject_id: (string) $subject->id,
            ca_score: $result?->ca_score !== null
                ? (float) $result->ca_score
                : null,
            exam_score: $result?->exam_score !== null
                ? (float) $result->exam_score
                : null,
            total_score: $result?->total_score !== null
                ? (float) $result->total_score
                : null,
            grade: $result?->grade,
        );
    }
}
