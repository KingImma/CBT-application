<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class CronController extends Controller
{
    private const DRAIN_QUEUES = 'exams,default,notifications,emails,imports';

    public function heartbeat(): JsonResponse
    {
        return ApiResponse::success(['time' => now()->toIso8601String()], 'alive');
    }

    public function schedule(): JsonResponse
    {
        return $this->runExclusively('cron:schedule', 90, function (): string {
            Artisan::call('schedule:run');

            return trim(Artisan::output());
        });
    }

    public function drain(): JsonResponse
    {
        return $this->runExclusively('cron:drain', 60, function (): string {
            Artisan::call('queue:work', [
                'connection' => 'horizon-redis',
                '--queue' => self::DRAIN_QUEUES,
                '--stop-when-empty' => true,
                '--max-time' => 25,
                '--tries' => 3,
            ]);

            return trim(Artisan::output());
        });
    }

    /**
     * One run at a time: if the previous ping is still working,
     * the new one backs off instead of doubling up.
     */
    private function runExclusively(string $lockName, int $seconds, callable $work): JsonResponse
    {
        ignore_user_abort(true);
        set_time_limit($seconds);

        $lock = Cache::lock($lockName, $seconds);

        if (! $lock->get()) {
            return ApiResponse::success(null, 'Previous run still in progress.');
        }

        try {
            return ApiResponse::success(['output' => $work()], 'Done.');
        } finally {
            $lock->release();
        }
    }
}