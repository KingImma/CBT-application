<?php

declare(strict_types=1);

namespace App\Domains\Questions\Data;

class FitbQuestionData extends QuestionData
{
    /**
     * @var array<int, array{content: string, case_sensitive: bool}>
     */
    public readonly array $acceptable_answers;

    public function __construct(
        string $id,
        string $type,
        string $content,
        string $content_format = 'plain_text',
        ?string $image_url = null,
        bool $is_active = true,
        ?string $subject_id = null,
        ?string $class_level_id = null,
        ?string $class_level_name = null,
        ?string $subject_name = null,
        array $acceptable_answers = [],
    ) {
        parent::__construct($id, $type, $content, $content_format, $image_url, $default_marks, $is_active, $subject_id, $class_level_id, $class_level_name, $subject_name);
        $this->acceptable_answers = $acceptable_answers;
    }
}
