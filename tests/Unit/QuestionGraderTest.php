<?php

use App\Support\QuestionGrader;
use Illuminate\Support\Collection;

it('grades mcq and true false by exact selected option ids', function (string $questionType): void {
    $options = collect([
        (object) ['id' => 'option-a', 'is_correct' => true],
        (object) ['id' => 'option-b', 'is_correct' => false],
        (object) ['id' => 'option-c', 'is_correct' => true],
    ]);

    $grader = new QuestionGrader;

    expect($grader->isCorrect($questionType, $options, ['option-c', 'option-a']))->toBeTrue()
        ->and($grader->isCorrect($questionType, $options, ['option-a']))->toBeFalse()
        ->and($grader->isCorrect($questionType, $options, ['option-a', 'option-b', 'option-c']))->toBeFalse();
})->with([
    'mcq' => 'mcq',
    'true_false' => 'true_false',
]);

it('grades fill in the blank answers against accepted options with case sensitivity', function (): void {
    $options = collect([
        (object) ['content' => 'Lagos', 'is_correct' => true, 'case_sensitive' => false],
        (object) ['content' => 'Abuja', 'is_correct' => true, 'case_sensitive' => true],
        (object) ['content' => 'Kano', 'is_correct' => false, 'case_sensitive' => false],
    ]);

    $grader = new QuestionGrader;

    expect($grader->isCorrect('fill_in_blank', $options, [], ' lagos '))->toBeTrue()
        ->and($grader->isCorrect('fill_in_blank', $options, [], 'Abuja'))->toBeTrue()
        ->and($grader->isCorrect('fill_in_blank', $options, [], 'abuja'))->toBeFalse()
        ->and($grader->isCorrect('fill_in_blank', $options, [], 'Kano'))->toBeFalse()
        ->and($grader->isCorrect('fill_in_blank', new Collection, [], 'Lagos'))->toBeFalse()
        ->and($grader->isCorrect('fill_in_blank', $options, [], null))->toBeFalse();
});
