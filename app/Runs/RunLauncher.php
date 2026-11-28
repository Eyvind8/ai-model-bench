<?php

namespace App\Runs;

use App\Enums\ResultStatus;
use App\Jobs\ExecuteResultJob;
use App\Models\Result;
use App\Models\Run;
use App\Runs\Execution\ResultExecutor;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Sleep;

/**
 * Starts execution of a planned run: through the queue (default) or inline (CLI --sync).
 */
final class RunLauncher
{
    public function __construct(
        private readonly Dispatcher $bus,
        private readonly ResultExecutor $executor,
    ) {}

    public function dispatch(Run $run): int
    {
        $ids = $this->pendingIds($run);
        foreach ($ids as $id) {
            $this->bus->dispatch(new ExecuteResultJob($id));
        }

        return count($ids);
    }

    /**
     * Inline execution: always picks the result that may run soonest, sleeps only when all must wait.
     *
     * @param  callable(Result): void|null  $onFinished
     */
    public function runInline(Run $run, ?callable $onFinished = null): void
    {
        $readyAt = array_fill_keys($this->pendingIds($run), 0);

        while ($readyAt !== []) {
            asort($readyAt);
            $id = (int) array_key_first($readyAt);
            $this->sleepUntil($readyAt[$id]);

            $result = Result::query()->findOrFail($id);
            $delay = $this->executor->execute($result);

            if ($delay === null) {
                unset($readyAt[$id]);
                $onFinished && $onFinished($result->refresh());
            } else {
                $readyAt[$id] = $this->nowMs() + $delay;
            }
        }
    }

    /**
     * @return list<int>
     */
    private function pendingIds(Run $run): array
    {
        return $run->results()->where('status', ResultStatus::Pending)->orderBy('id')->pluck('id')->all();
    }

    private function sleepUntil(int $timestampMs): void
    {
        $wait = $timestampMs - $this->nowMs();
        if ($wait > 0) {
            Sleep::for($wait)->milliseconds();
        }
    }

    private function nowMs(): int
    {
        return (int) now()->getTimestampMs();
    }
}
