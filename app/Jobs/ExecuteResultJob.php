<?php

namespace App\Jobs;

use App\Models\Result;
use App\Runs\Execution\ResultExecutor;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Executes one result. When a rate limit or backoff asks to wait, the job is released
 * back to the queue with a delay, so the worker keeps serving other providers.
 */
class ExecuteResultJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 150;

    public function __construct(public readonly int $resultId) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(ResultExecutor $executor): void
    {
        $result = Result::query()->find($this->resultId);
        if ($result === null) {
            return;
        }

        $delayMs = $executor->execute($result);
        if ($delayMs !== null) {
            $this->release((int) ceil($delayMs / 1000));
        }
    }
}
