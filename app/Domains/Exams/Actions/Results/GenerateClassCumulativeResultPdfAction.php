<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Results;

use App\Domains\Exams\Support\ResolveSchoolPdfHeader;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use App\Models\Tenant\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Str;
use RuntimeException;

final class GenerateClassCumulativeResultPdfAction
{
    public function __construct(
        private BuildStudentCumulativeResult $build,
        private ResolveSchoolPdfHeader $schoolHeader,
    ) {}

    /**
     * Generate a cumulative result PDF containing one result sheet
     * for every student in the given class arm.
     */
    public function execute(ClassArm $arm, Exam $exam): DomPdf
    {
        $exam->loadMissing('term.academicSession');

        $session = $exam->term->academicSession;

        $students = User::query()
            ->where('role', 'student')
            ->whereHas('studentProfile', fn ($query) => $query
                ->where('class_level_id', $arm->class_level_id)
                ->where('class_arm_id', $arm->id)
            )
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        throw_if(
            $students->isEmpty(),
            new RuntimeException('No students found for this class arm.')
        );

        $results = $students->map(
            fn (User $student) => $this->build->execute($student, $session)
        );

        return Pdf::loadView('pdf.exam-cumulative-result-bulk', [
            'results' => $results,
            'school' => $this->schoolHeader->execute(),
        ])->setPaper('a4');
    }

    public function filename(ClassArm $arm, Exam $exam): string
    {
        return Str::slug(
            "{$exam->title}-{$arm->classLevel->name}-{$arm->name}-cumulative"
        ) . '.pdf';
    }
}
