<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use App\Models\Tenant\Topic;
use Illuminate\Support\Facades\DB;

class SyncExamTopicsAction
{
    public function execute(Exam $exam, array $topicIds, array $weights = []): void
    {
        DB::transaction(function () use ($exam, $topicIds, $weights) {
            $exam->topics()->detach();

            foreach ($topicIds as $topicId) {
                $topic = Topic::findOrFail($topicId);
                $exam->topics()->attach($topic->id, [
                    'weight' => $weights[$topicId] ?? null,
                ]);
            }
        });
    }
}
