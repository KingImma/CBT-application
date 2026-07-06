<?php

declare(strict_types=1);

namespace App\Data\Question;

class McqQuestionData extends QuestionData
{
    /**
     * @var array<int, array{id: string, label: ?string, content: string, image_url: ?string, is_correct: bool, order: ?int, match_pair: ?string, case_sensitive: ?bool}>
     */
    public readonly array $options;

    public readonly bool $allow_multiple_answers;

    public function __construct(
        string $id,
        string $type,
        string $content,
        string $content_format = 'plain_text',
        ?string $image_url = null,
        float $default_marks = 0,
        bool $is_active = true,
        ?string $subject_id = null,
        ?string $class_level_id = null,
        ?string $class_level_name = null,
        ?string $subject_name = null,
        array $options = [],
        bool $allow_multiple_answers = false,
    ) {
        parent::__construct($id, $type, $content, $content_format, $image_url, $default_marks, $is_active, $subject_id, $class_level_id, $class_level_name, $subject_name);
        $this->options = $options;
        $this->allow_multiple_answers = $allow_multiple_answers;
    }
}
