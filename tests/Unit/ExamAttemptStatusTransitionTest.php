<?php

declare(strict_types=1);

use App\Enums\ExamAttemptStatus;
use App\Exceptions\Domain\Exam\ExamAttemptStatusTransitionException;
use App\Support\Exam\ExamAttemptStatusTransition;

it('allows InProgress to Submitted', function () {
    expect(ExamAttemptStatusTransition::isAllowed(
        ExamAttemptStatus::InProgress->value,
        ExamAttemptStatus::Submitted->value,
    ))->toBeTrue();
});

it('allows InProgress to Timed_out', function () {
    expect(ExamAttemptStatusTransition::isAllowed(
        ExamAttemptStatus::InProgress->value,
        ExamAttemptStatus::Timed_out->value,
    ))->toBeTrue();
});

it('allows Submitted to Grading', function () {
    expect(ExamAttemptStatusTransition::isAllowed(
        ExamAttemptStatus::Submitted->value,
        ExamAttemptStatus::Grading->value,
    ))->toBeTrue();
});

it('allows Grading to Graded', function () {
    expect(ExamAttemptStatusTransition::isAllowed(
        ExamAttemptStatus::Grading->value,
        ExamAttemptStatus::Graded->value,
    ))->toBeTrue();
});

it('allows Grading to Failed', function () {
    expect(ExamAttemptStatusTransition::isAllowed(
        ExamAttemptStatus::Grading->value,
        ExamAttemptStatus::Failed->value,
    ))->toBeTrue();
});

it('allows Timed_out to Submitted', function () {
    expect(ExamAttemptStatusTransition::isAllowed(
        ExamAttemptStatus::Timed_out->value,
        ExamAttemptStatus::Submitted->value,
    ))->toBeTrue();
});

it('allows Failed to Grading', function () {
    expect(ExamAttemptStatusTransition::isAllowed(
        ExamAttemptStatus::Failed->value,
        ExamAttemptStatus::Grading->value,
    ))->toBeTrue();
});

it('rejects direct InProgress to Graded', function () {
    expect(ExamAttemptStatusTransition::isAllowed(
        ExamAttemptStatus::InProgress->value,
        ExamAttemptStatus::Graded->value,
    ))->toBeFalse();
});

it('rejects Graded to any other status', function () {
    expect(ExamAttemptStatusTransition::isAllowed(
        ExamAttemptStatus::Graded->value,
        ExamAttemptStatus::InProgress->value,
    ))->toBeFalse();
});

it('rejects Submitted back to InProgress', function () {
    expect(ExamAttemptStatusTransition::isAllowed(
        ExamAttemptStatus::Submitted->value,
        ExamAttemptStatus::InProgress->value,
    ))->toBeFalse();
});

it('throws on illegal transition', function () {
    ExamAttemptStatusTransition::assertAllowed(
        ExamAttemptStatus::InProgress->value,
        ExamAttemptStatus::Graded->value,
    );
})->throws(ExamAttemptStatusTransitionException::class);

it('does not throw on legal transition', function () {
    ExamAttemptStatusTransition::assertAllowed(
        ExamAttemptStatus::Submitted->value,
        ExamAttemptStatus::Grading->value,
    );

    expect(true)->toBeTrue();
});
