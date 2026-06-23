<?php

declare(strict_types=1);

namespace App\Data\Question;

class McqQuestionData extends QuestionData
{
    /** @var array<int, QuestionOptionData> */
    public readonly array $options;

    public function __construct(
        string $id,
        string $type,
        string $content,
        ?string $image_url,
        float $default_marks,
        bool $is_active,
        ?string $subject_id,
        ?string $class_level_id,
        array $options,
    ) {
        parent::__construct($id, $type, $content, $image_url, $default_marks, $is_active, $subject_id, $class_level_id);
        $this->options = $options;
    }
}
