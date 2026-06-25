<?php 

declare(strict_types=1);

namespace App\Mail\Data;

use App\Models\Tenant\ExamAttempt;


class ExamResultMailData
{
    public function __construct(
        public readonly string $studentName,
        public readonly string $examTitle,
        public readonly string $className,
        public readonly string $subjectName,
        public readonly float  $score,
        public readonly float  $totalMarks,
        public readonly float  $percentage,
        public readonly ?string $grade,
        public readonly string  $status,
        public readonly string $releasedOn,
        public readonly string $portalLink,
        public readonly string $schoolName,
    ) {}

    public static function fromAttempt(ExamAttempt $attempt, string $portalLink, string $schoolName): self
    {
        $exam = $attempt->exam;
        $student = $attempt->student;
        $passed = $attempt->percentage_score >= $exam->pass_mark;
        
        return new self(
            studentName: "{$student->first_name} {$student->last_name}",
            examTitle: $exam->title,
            className: $exam->classLevel?->name ?? '-',
            subjectName: $exam->subject?->name ?? '-',
            score: (float) $attempt->total_score,
            totalMarks: (float) $exam->total_marks,
            percentage: (float) $attempt->percentage_score,
            grade: $attempt->grade,
            status: $passed ? 'Passed' : 'Failed',
            releasedOn: now()->format('d-M-Y'),
            portalLink: $portalLink,
            schoolName: $schoolName,
        );
    }
}
