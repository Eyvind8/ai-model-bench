<?php

namespace App\Jobs;

use App\Evaluation\Judge\JudgeExecutor;
use App\Models\LlmModel;
use App\Models\Result;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class JudgeResultJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 150;

    public function __construct(
        public readonly int $resultId,
        public readonly int $judgeModelId,
    ) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(JudgeExecutor $executor): void
    {
        $result = Result::query()->find($this->resultId);
        $judge = LlmModel::query()->with('provider')->find($this->judgeModelId);
        if ($result === null || $judge === null) {
            return;
        }

        $delayMs = $executor->execute($result, $judge);
        if ($delayMs !== null) {
            $this->release((int) ceil($delayMs / 1000));
        }
    }
}
