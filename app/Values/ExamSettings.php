<?php

declare(strict_types=1);

namespace App\Values;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Carbon;

class ExamSettings implements Castable
{
    public function __construct(
        public readonly bool $randomizeQuestions = false,
        public readonly bool $showResultImmediately = false,
        public readonly ?Carbon $resultsReleaseDate = null,
        public readonly bool $requireAttendance = true,
        public readonly string $distribution = 'pooled',
        public readonly array $topicWeights = [],
    ) {}

    public static function fromArray(?array $data): self
    {
        if ($data === null) {
            return new self;
        }

        return new self(
            randomizeQuestions: (bool) ($data['randomize_questions'] ?? false),
            showResultImmediately: (bool) ($data['show_result_immediately'] ?? false),
            resultsReleaseDate: isset($data['results_release_date']) ? Carbon::parse($data['results_release_date']) : null,
            requireAttendance: (bool) ($data['require_attendance'] ?? true),
            distribution: (string) ($data['distribution'] ?? 'pooled'),
            topicWeights: (array) ($data['topic_weights'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'randomize_questions' => $this->randomizeQuestions,
            'show_result_immediately' => $this->showResultImmediately,
            'results_release_date' => $this->resultsReleaseDate?->toDateTimeString(),
            'require_attendance' => $this->requireAttendance,
            'distribution' => $this->distribution,
            'topic_weights' => $this->topicWeights,
        ];
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get($model, string $key, $value, array $attributes): ExamSettings
            {
                return ExamSettings::fromArray(json_decode($value, true));
            }

            public function set($model, string $key, $value, array $attributes): string
            {
                if ($value instanceof ExamSettings) {
                    return json_encode($value->toArray());
                }

                if (is_array($value)) {
                    return json_encode($value);
                }

                return $value;
            }
        };
    }
}
