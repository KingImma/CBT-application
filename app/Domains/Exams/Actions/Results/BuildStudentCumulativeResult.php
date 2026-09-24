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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\LaravelData\DataCollection;

final class BuildStudentCumulativeResult
{
    public function __construct(
        private StudentCumulativeResultQuery $query,
        private ResolveCAComponentTypes $resolveCaTypes,
    ) {}

    /**
     * The standing cumulative report: every term of the session up to and
     * including the current one. A concluded session (no current term) is
     * reported in full.
     */
    public function execute(User $student, AcademicSession $session): StudentCumulativeResultData
    {
        return $this->build($student, $session->name, $this->sessionTermsUpToCurrent($session));
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

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function sessionTermsUpToCurrent(AcademicSession $session): array
    {
        $terms = $session->terms()
            ->orderBy('start_date')
            ->get(['id', 'name', 'is_current']);

        $currentIndex = $terms->search(fn ($term) => (bool) $term->is_current);

        if ($currentIndex !== false) {
            $terms = $terms->slice(0, $currentIndex + 1);
        }

        return $terms
            ->map(fn ($term) => ['id' => $term->id, 'name' => $term->name])
            ->values()
            ->all();
    }

    /** @param array<int, array{id: string, name: string}> $terms */
    private function build(User $student, string $sessionName, array $terms): StudentCumulativeResultData
    {
        $profile = $student->studentProfile()->with(['classLevel', 'classArm'])->firstOrFail();
        $grades = $this->defaultGradingScale()?->grades;
        $caTypes = $this->resolveCaTypes->execute();
        $termIds = array_column($terms, 'id');

        $rows = $this->query->execute(
            $student->id,
            $profile->class_level_id,
            $termIds,
            $caTypes,
        );

        /** @var array<int, StudentCumulativeSubjectRowData> $subjects */
        $subjects = array_map(
            fn ($row) => new StudentCumulativeSubjectRowData(
                subject_id: $row->subject_id,
                subject_name: $row->subject_name,
                terms: $this->withGrades(json_decode($row->terms, true), $grades),
            ),
            $rows,
        );

        $rank = $this->query->classRank(
            $student->id,
            $profile->class_level_id,
            $profile->class_arm_id,
            $termIds,
            $caTypes,
        );

        $totalObtained = $this->totalObtained($subjects);
        $totalObtainable = $this->totalObtainable($subjects, count($terms));
        $average = $totalObtainable > 0
            ? round($totalObtained / $totalObtainable * 100, 2)
            : 0.0;

        return new StudentCumulativeResultData(
            student_id: $student->id,
            admission_number: $profile->admission_number,
            student_name: "{$student->first_name} {$student->last_name}",
            class_level_name: $profile->classLevel->name,
            class_arm_name: $profile->classArm?->name,
            academic_session_name: $sessionName,
            terms: $terms,
            subjects: StudentCumulativeSubjectRowData::collect($subjects, DataCollection::class),
            gender: $profile->gender,
            number_in_class: $rank['number_in_class'],
            position: $rank['position'],
            total_obtained: $totalObtained,
            total_obtainable: $totalObtainable,
            average: $average,
            evaluation: $this->describe($average, $grades),
            school_section: $this->schoolSection($profile->classLevel->name),
            grades: $grades ?? [],
        );
    }

    /**
     * @param  array<string, array{ca: ?float, exam: ?float, total: float}>  $terms
     * @return array<string, array{ca: ?float, exam: ?float, total: float, grade: ?string, remark: ?string}>
     */
    private function withGrades(array $terms, ?array $grades): array
    {
        foreach ($terms as &$cell) {
            $cell['grade'] = ResolveGrade::execute((float) $cell['total'], $grades);
            $cell['remark'] = $this->remarkFor($cell['grade'], $grades);
        }

        return $terms;
    }

    /** @param array<int, StudentCumulativeSubjectRowData> $subjects */
    private function totalObtained(array $subjects): float
    {
        $total = 0.0;

        foreach ($subjects as $subject) {
            foreach ($subject->terms as $cell) {
                $total += (float) $cell['total'];
            }
        }

        return round($total, 2);
    }

    /**
     * Every graded subject is worth 100 marks per term, so the ceiling is the
     * number of subjects with a result x 100 x terms in range.
     *
     * @param array<int, StudentCumulativeSubjectRowData> $subjects
     */
    private function totalObtainable(array $subjects, int $termCount): float
    {
        $scoredSubjects = (new Collection($subjects))
            ->filter(fn (StudentCumulativeSubjectRowData $row) => $row->terms !== [])
            ->count();

        return (float) ($scoredSubjects * 100 * max($termCount, 1));
    }

    private function remarkFor(?string $grade, ?array $grades): ?string
    {
        if ($grade === null || $grades === null) {
            return null;
        }

        foreach ($grades as $definition) {
            if (($definition['label'] ?? null) === $grade) {
                return $definition['remark'] ?? null;
            }
        }

        return null;
    }

    /** The qualitative label printed as EVALUATION for an overall average. */
    private function describe(float $average, ?array $grades): ?string
    {
        $grade = ResolveGrade::execute($average, $grades);

        return $this->remarkFor($grade, $grades) ?? $grade;
    }

    private function schoolSection(string $classLevelName): ?string
    {
        $normalized = strtoupper($classLevelName);

        return match (true) {
            str_contains($normalized, 'JSS') || str_contains($normalized, 'JUNIOR')
                => 'JUNIOR SECONDARY',
            str_contains($normalized, 'SSS') || str_contains($normalized, 'SENIOR')
                => 'SENIOR SECONDARY',
            default => null,
        };
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
