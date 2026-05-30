<?php

declare(strict_types=1);

namespace App\Actors;

class ActorRegistry
{
    /**
     * Get or construct actor for given entity ID.
     * In FPM: constructs per request but Redis cache = fast cold start.
     * In Swoole/Octane: can hold true in-memory map.
     */
    public static function examSession(string $attemptId): ExamSessionActor
    {
        return new ExamSessionActor($attemptId);
    }
}
