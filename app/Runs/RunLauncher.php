<?php

namespace App\Runs;

use App\Enums\ResultStatus;
use App\Jobs\ExecuteResultJob;
use App\Models\Result;
use App\Models\Run;
use App\Runs\Execution\ResultExecutor;
use App\Support\InlineScheduler;
use Illuminate\Contracts\Bus\Dispatcher;

/**
 * Starts execution of a planned run: through the queue (default) or inline (CLI --sync).
 */
final class RunLauncher
{
    public function __construct(
        private readonly Dispatcher $bus,
        private readonly ResultExecutor $executor,
        private readonly InlineScheduler $scheduler,
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
     * @param  callable(Result): void|null  $onFinished
     */
    public function runInline(Run $run, ?callable $onFinished = null): void
    {
        $this->scheduler->run(
            $this->pendingIds($run),
            fn (int $id) => $this->executor->execute(Result::query()->findOrFail($id)),
            $onFinished ? fn (int $id) => $onFinished(Result::query()->findOrFail($id)) : null,
        );
    }

    /**
     * @return list<int>
     */
    private function pendingIds(Run $run): array
    {
        return $run->results()->where('status', ResultStatus::Pending)->orderBy('id')->pluck('id')->all();
    }
}
