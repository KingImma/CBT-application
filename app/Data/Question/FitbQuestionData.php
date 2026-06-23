<?php

declare(strict_types=1);

namespace App\Data\Question;

class FitbQuestionData extends QuestionData
{
    /** @var array<int, FitbAcceptableAnswerData> */
    public readonly array $acceptable_answers;

    public function __construct(
        string $id,
        string $type,
        string $content,
        ?string $image_url,
        float $default_marks,
        bool $is_active,
        ?string $subject_id,
        ?string $class_level_id,
        array $acceptable_answers,
    ) {
        parent::__construct($id, $type, $content, $image_url, $default_marks, $is_active, $subject_id, $class_level_id);
        $this->acceptable_answers = $acceptable_answers;
    }
}
