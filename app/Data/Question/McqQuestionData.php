<?php

declare(strict_types=1);

namespace App\Data\Question;

class McqQuestionData extends QuestionData
{
    /**
     * @var array<int, array{id: string, label: ?string, content: string, image_url: ?string, is_correct: bool, order: ?int, match_pair: ?string, case_sensitive: ?bool}>
     */
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
