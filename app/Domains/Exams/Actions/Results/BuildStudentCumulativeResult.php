<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Results;

use App\Domains\Exams\Actions\ResolveGrade;
use App\Domains\Exams\Data\Output\Results\StudentCumulativeResultData;
use App\Domains\Exams\Data\Output\Results\StudentCumulativeSubjectRowData;
use App\Domains\Exams\Queries\StudentCumulativeResultQuery;
use App\Domains\Exams\Support\ResolveCAComponentTypes;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\GradingScale;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Cache;
use Spatie\LaravelData\DataCollection;

final class BuildStudentCumulativeResult
{
    public function __construct(
        private StudentCumulativeResultQuery $query,
        private ResolveCAComponentTypes $resolveCaTypes,
    ) {}

    /** All terms in the session — the standing cumulative report. */
    public function execute(User $student, AcademicSession $session): StudentCumulativeResultData
    {
        $terms = $session->terms()->orderBy('start_date')->get(['id', 'name'])
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])
            ->all();

        return $this->build($student, $session->name, $terms);
    }

    /** One term only — used for the single-result "per subject" view. */
    public function executeForTerm(User $student, Term $term): StudentCumulativeResultData
    {
        $term->loadMissing('academicSession');

        return $this->build(
            $student,
            $term->academicSession->name,
            [['id' => $term->id, 'name' => $term->name]],
        );
    }

    /** @param array<int, array{id: string, name: string}> $terms */
    private function build(User $student, string $sessionName, array $terms): StudentCumulativeResultData
    {
        $profile = $student->studentProfile()->with(['classLevel', 'classArm'])->firstOrFail();
        $grades = $this->defaultGradingScale()?->grades;

        $rows = $this->query->execute(
            $student->id,
            $profile->class_level_id,
            array_column($terms, 'id'),
            $this->resolveCaTypes->execute(),
        );

        return new StudentCumulativeResultData(
            student_id: $student->id,
            admission_number: $profile->admission_number,
            student_name: "{$student->first_name} {$student->last_name}",
            class_level_name: $profile->classLevel->name,
            class_arm_name: $profile->classArm?->name,
            academic_session_name: $sessionName,
            terms: $terms,
            subjects: StudentCumulativeSubjectRowData::collect(array_map(
                fn ($row) => new StudentCumulativeSubjectRowData(
                    subject_id: $row->subject_id,
                    subject_name: $row->subject_name,
                    terms: $this->withGrades(json_decode($row->terms, true), $grades),
                ),
                $rows,
            ), DataCollection::class),
        );
    }

    /** @param array<string, array{ca: ?float, exam: ?float, total: float}> $terms */
    private function withGrades(array $terms, ?array $grades): array
    {
        foreach ($terms as &$cell) {
            $cell['grade'] = ResolveGrade::execute((float) $cell['total'], $grades);
        }

        return $terms;
    }

    private function defaultGradingScale(): ?GradingScale
    {
        return Cache::remember(
            'grading_scale:default:'.tenant('id'),
            now()->addDay(),
            fn () => GradingScale::where('is_default', true)->first(),
        );
    }
}