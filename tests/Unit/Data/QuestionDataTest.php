<?php

declare(strict_types=1);

use App\Domains\Questions\Data\FitbQuestionData;
use App\Domains\Questions\Data\McqQuestionData;
use App\Domains\Questions\Data\QuestionData;
use App\Domains\Questions\Data\TrueFalseQuestionData;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Question;
use App\Models\Tenant\QuestionOption;
use App\Models\Tenant\Subject;

beforeEach(function () {
    // Build models in-memory to avoid needing a real database connection.
    // We create lightweight Question instances and attach relationships
    // via setRelation so fromQuestion() can read them normally.

    $this->subject = new Subject(['name' => 'Mathematics']);
    $this->subject->id = 'subj-uuid-1';

    $this->classLevel = new ClassLevel(['name' => 'Grade 10']);
    $this->classLevel->id = 'cl-uuid-1';
});

function makeQuestion(array $attributes = []): Question
{
    // Using new + force-fill to avoid UUID generation / DB writes
    $q = new Question;
    $q->id = $attributes['id'] ?? 'q-uuid-1';
    $q->type = $attributes['type'] ?? 'mcq';
    $q->content = $attributes['content'] ?? 'Test question';
    $q->content_format = $attributes['content_format'] ?? 'plain_text';
    $q->image_url = $attributes['image_url'] ?? null;
    $q->default_marks = $attributes['default_marks'] ?? 1;
    $q->is_active = $attributes['is_active'] ?? true;
    $q->subject_id = $attributes['subject_id'] ?? null;
    $q->class_level_id = $attributes['class_level_id'] ?? null;

    if (isset($attributes['subject_id'])) {
        $q->setRelation(
            'subject',
            new Subject([
                'name' => $attributes['subject_name'] ?? 'Mathematics',
            ]),
        );
    }
    if (isset($attributes['class_level_id'])) {
        $q->setRelation(
            'classLevel',
            new ClassLevel([
                'name' => $attributes['class_level_name'] ?? 'Grade 10',
            ]),
        );
    }

    return $q;
}

function makeOption(array $attrs = []): QuestionOption
{
    $o = new QuestionOption;
    $o->id = $attrs['id'] ?? 'opt-'.fake()->uuid();
    $o->label = $attrs['label'] ?? null;
    $o->content = $attrs['content'] ?? 'Option';
    $o->content_format = $attrs['content_format'] ?? 'plain_text';
    $o->image_url = $attrs['image_url'] ?? null;
    $o->is_correct = $attrs['is_correct'] ?? false;
    $o->order = $attrs['order'] ?? 1;
    $o->match_pair = $attrs['match_pair'] ?? null;

    // The QuestionOption model has an appended case_sensitive attribute
    // derived from match_pair. We set the underlying attribute directly.
    $o->forceFill(['case_sensitive' => $attrs['case_sensitive'] ?? null]);

    return $o;
}

// ─── MCQ tests ───────────────────────────────────────────────────────

it(
    'creates McqQuestionData with allow_multiple_answers false when single correct option',
    function () {
        $question = makeQuestion([
            'subject_id' => 'subj-uuid-1',
            'class_level_id' => 'cl-uuid-1',
            'subject_name' => 'Mathematics',
            'class_level_name' => 'Grade 10',
        ]);
        $question->setRelation(
            'options',
            collect([
                makeOption([
                    'label' => 'A',
                    'content' => '3',
                    'is_correct' => false,
                    'order' => 1,
                ]),
                makeOption([
                    'label' => 'B',
                    'content' => '4',
                    'is_correct' => true,
                    'order' => 2,
                ]),
                makeOption([
                    'label' => 'C',
                    'content' => '5',
                    'is_correct' => false,
                    'order' => 3,
                ]),
            ]),
        );

        $result = QuestionData::fromQuestion($question);

        expect($result)
            ->toBeInstanceOf(McqQuestionData::class)
            ->and($result->allow_multiple_answers)
            ->toBeFalse()
            ->and($result->id)
            ->toBe($question->id)
            ->and($result->type)
            ->toBe('mcq')
            ->and($result->content)
            ->toBe('Test question')
            ->and($result->content_format)
            ->toBe('plain_text')
            ->and($result->default_marks)
            ->toBe(1.0)
            ->and($result->is_active)
            ->toBeTrue()
            ->and($result->subject_id)
            ->toBe('subj-uuid-1')
            ->and($result->class_level_id)
            ->toBe('cl-uuid-1')
            ->and($result->class_level_name)
            ->toBe('Grade 10')
            ->and($result->subject_name)
            ->toBe('Mathematics')
            ->and($result->options)
            ->toHaveCount(3);
    },
);

it(
    'creates McqQuestionData with allow_multiple_answers true when multiple correct options',
    function () {
        $question = makeQuestion([
            'content' => 'Select prime numbers',
            'default_marks' => 2,
            'subject_id' => 'subj-uuid-1',
            'class_level_id' => 'cl-uuid-1',
        ]);
        $question->setRelation(
            'options',
            collect([
                makeOption([
                    'label' => 'A',
                    'content' => '2',
                    'is_correct' => true,
                    'order' => 1,
                ]),
                makeOption([
                    'label' => 'B',
                    'content' => '4',
                    'is_correct' => false,
                    'order' => 2,
                ]),
                makeOption([
                    'label' => 'C',
                    'content' => '7',
                    'is_correct' => true,
                    'order' => 3,
                ]),
            ]),
        );

        $result = QuestionData::fromQuestion($question);

        expect($result)
            ->toBeInstanceOf(McqQuestionData::class)
            ->and($result->allow_multiple_answers)
            ->toBeTrue()
            ->and($result->options)
            ->toHaveCount(3)
            ->sequence(
                fn ($opt) => $opt->label->toBe('A')->is_correct->toBeTrue(),
                fn ($opt) => $opt->label->toBe('B')->is_correct->toBeFalse(),
                fn ($opt) => $opt->label->toBe('C')->is_correct->toBeTrue(),
            );
    },
);

// ─── True/False tests ────────────────────────────────────────────────

it('creates TrueFalseQuestionData with both options', function () {
    $question = makeQuestion([
        'type' => 'true_false',
        'content' => 'The earth is flat.',
        'subject_id' => 'subj-uuid-1',
        'class_level_id' => 'cl-uuid-1',
    ]);
    $question->setRelation(
        'options',
        collect([
            makeOption([
                'label' => 'True',
                'content' => 'True',
                'is_correct' => false,
                'order' => 1,
            ]),
            makeOption([
                'label' => 'False',
                'content' => 'False',
                'is_correct' => true,
                'order' => 2,
            ]),
        ]),
    );

    $result = QuestionData::fromQuestion($question);

    expect($result)
        ->toBeInstanceOf(TrueFalseQuestionData::class)
        ->and($result->type)
        ->toBe('true_false')
        ->and($result->content)
        ->toBe('The earth is flat.')
        ->and($result->options)
        ->toHaveCount(2);
});

it(
    'creates FitbQuestionData with only correct answers as acceptable_answers',
    function () {
        $question = makeQuestion([
            'type' => 'fill_in_blank',
            'content' => 'What is the capital of Nigeria?',
            'default_marks' => 2,
            'subject_id' => 'subj-uuid-1',
            'class_level_id' => 'cl-uuid-1',
        ]);
        $question->setRelation(
            'options',
            collect([
                makeOption([
                    'label' => null,
                    'content' => 'Lagos',
                    'is_correct' => false,
                    'order' => 1,
                ]),
                makeOption([
                    'label' => null,
                    'content' => 'Abuja',
                    'is_correct' => true,
                    'order' => 2,
                ]),
            ]),
        );

        $result = QuestionData::fromQuestion($question);

        expect($result)
            ->toBeInstanceOf(FitbQuestionData::class)
            ->and($result->type)
            ->toBe('fill_in_blank')
            ->and($result->acceptable_answers)
            ->toHaveCount(1)
            ->and($result->acceptable_answers[0]['content'])
            ->toBe('Abuja');
    },
);

it('maps options with correct structure for McqQuestionData', function () {
    $question = makeQuestion([
        'content' => 'Which is correct?',
        'subject_id' => 'subj-uuid-1',
        'class_level_id' => 'cl-uuid-1',
    ]);
    $question->setRelation(
        'options',
        collect([
            makeOption([
                'label' => 'A',
                'content' => 'Option A',
                'image_url' => 'http://example.com/a.png',
                'is_correct' => true,
                'order' => 1,
                'match_pair' => null,
                'case_sensitive' => false,
            ]),
        ]),
    );

    $result = QuestionData::fromQuestion($question);

    expect($result->options[0])->toMatchArray([
        'label' => 'A',
        'content' => 'Option A',
        'image_url' => 'http://example.com/a.png',
        'is_correct' => true,
        'order' => 1,
        'match_pair' => null,
        'case_sensitive' => false,
    ]);
});

// ─── Edge cases ─────────────────────────────────────────────────────

it('throws InvalidArgumentException for unknown question type', function () {
    $question = makeQuestion([
        'type' => 'essay',
        'content' => 'Write an essay.',
        'default_marks' => 10,
    ]);
    $question->setRelation(
        'options',
        collect([
            makeOption([
                'label' => 'A',
                'content' => 'Option',
                'is_correct' => true,
                'order' => 1,
            ]),
        ]),
    );

    QuestionData::fromQuestion($question);
})->throws(InvalidArgumentException::class, 'Unknown question type: essay');

it(
    'resolves null subject and class level names when relationships are unset',
    function () {
        $question = makeQuestion(['content' => 'No subject question']);
        $question->setRelation(
            'options',
            collect([
                makeOption([
                    'label' => 'A',
                    'content' => 'Yes',
                    'is_correct' => true,
                    'order' => 1,
                ]),
                makeOption([
                    'label' => 'B',
                    'content' => 'No',
                    'is_correct' => false,
                    'order' => 2,
                ]),
            ]),
        );

        $result = QuestionData::fromQuestion($question);

        expect($result->subject_name)
            ->toBeNull()
            ->and($result->class_level_name)
            ->toBeNull()
            ->and($result->subject_id)
            ->toBeNull()
            ->and($result->class_level_id)
            ->toBeNull();
    },
);

it('handles image_url being null', function () {
    $question = makeQuestion([
        'content' => 'No image question',
        'image_url' => null,
        'subject_id' => 'subj-uuid-1',
        'class_level_id' => 'cl-uuid-1',
    ]);
    $question->setRelation(
        'options',
        collect([
            makeOption([
                'label' => 'A',
                'content' => 'Yes',
                'is_correct' => true,
                'order' => 1,
            ]),
            makeOption([
                'label' => 'B',
                'content' => 'No',
                'is_correct' => false,
                'order' => 2,
            ]),
        ]),
    );

    $result = QuestionData::fromQuestion($question);

    expect($result->image_url)->toBeNull();
});

it('preserves options order as provided by the relation', function () {
    $question = makeQuestion([
        'content' => 'Order matters',
        'subject_id' => 'subj-uuid-1',
        'class_level_id' => 'cl-uuid-1',
    ]);
    // In production the options relation query applies orderBy('order'),
    // so the collection always arrives sorted. We simulate that here.
    $question->setRelation(
        'options',
        collect([
            makeOption([
                'label' => 'A',
                'content' => 'First',
                'is_correct' => true,
                'order' => 1,
            ]),
            makeOption([
                'label' => 'B',
                'content' => 'Second',
                'is_correct' => false,
                'order' => 2,
            ]),
            makeOption([
                'label' => 'C',
                'content' => 'Third',
                'is_correct' => false,
                'order' => 3,
            ]),
        ]),
    );

    $result = QuestionData::fromQuestion($question);

    // The method maps the options in the same order as the collection
    expect($result->options[0]['label'])
        ->toBe('A')
        ->and($result->options[1]['label'])
        ->toBe('B')
        ->and($result->options[2]['label'])
        ->toBe('C');
});

it('maps content_format as latex when set on question', function () {
    $question = makeQuestion([
        'content' => '\frac{x^2}{y^2}',
        'content_format' => 'latex',
        'subject_id' => 'subj-uuid-1',
        'class_level_id' => 'cl-uuid-1',
    ]);
    $question->setRelation(
        'options',
        collect([
            makeOption([
                'label' => 'A',
                'content' => '\sqrt{4}',
                'is_correct' => true,
                'order' => 1,
            ]),
            makeOption([
                'label' => 'B',
                'content' => '\sqrt{9}',
                'is_correct' => false,
                'order' => 2,
            ]),
        ]),
    );

    $result = QuestionData::fromQuestion($question);

    expect($result)
        ->toBeInstanceOf(McqQuestionData::class)
        ->and($result->content_format)
        ->toBe('latex')
        ->and($result->content)
        ->toBe('\frac{x^2}{y^2}');
});

it('maps content_format as latex when set on options', function () {
    $question = makeQuestion([
        'subject_id' => 'subj-uuid-1',
        'class_level_id' => 'cl-uuid-1',
    ]);
    $question->setRelation(
        'options',
        collect([
            makeOption([
                'label' => 'A',
                'content' => '\alpha + \beta',
                'content_format' => 'latex',
                'is_correct' => true,
                'order' => 1,
            ]),
            makeOption([
                'label' => 'B',
                'content' => 'Plain text',
                'content_format' => 'plain_text',
                'is_correct' => false,
                'order' => 2,
            ]),
        ]),
    );

    $result = QuestionData::fromQuestion($question);

    expect($result->options[0]['content_format'])
        ->toBe('latex')
        ->and($result->options[0]['content'])
        ->toBe('\alpha + \beta')
        ->and($result->options[1]['content_format'])
        ->toBe('plain_text');
});

it(
    'handles fill_in_blank case_sensitive flag from match_pair json',
    function () {
        $question = makeQuestion([
            'type' => 'fill_in_blank',
            'content' => 'Case sensitive FITB',
            'default_marks' => 1,
            'subject_id' => 'subj-uuid-1',
            'class_level_id' => 'cl-uuid-1',
        ]);
        $question->setRelation(
            'options',
            collect([
                makeOption([
                    'label' => null,
                    'content' => 'Exact',
                    'is_correct' => true,
                    'order' => 1,
                    'match_pair' => json_encode(['case_sensitive' => true]),
                    'case_sensitive' => true,
                ]),
                makeOption([
                    'label' => null,
                    'content' => 'Any',
                    'is_correct' => true,
                    'order' => 2,
                    'match_pair' => null,
                    'case_sensitive' => false,
                ]),
            ]),
        );

        $result = QuestionData::fromQuestion($question);

        expect($result)
            ->toBeInstanceOf(FitbQuestionData::class)
            ->and($result->acceptable_answers)
            ->toHaveCount(2)
            ->and($result->acceptable_answers[0]['case_sensitive'])
            ->toBeTrue()
            ->and($result->acceptable_answers[1]['case_sensitive'])
            ->toBeFalse();
    },
);
