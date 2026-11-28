<?php

namespace App\Support;

use Illuminate\Support\Sleep;

/**
 * Runs tasks inline that may ask to be retried later (rate limits, backoff):
 * always picks the task that may run soonest and sleeps only when all must wait.
 */
final class InlineScheduler
{
    /**
     * @param  list<int>  $ids
     * @param  callable(int): ?int  $execute  returns ms to wait before retrying, null when the task is finished
     * @param  callable(int): void|null  $onFinished
     */
    public function run(array $ids, callable $execute, ?callable $onFinished = null): void
    {
        $readyAt = array_fill_keys($ids, 0);

        while ($readyAt !== []) {
            asort($readyAt);
            $id = (int) array_key_first($readyAt);
            $this->sleepUntil($readyAt[$id]);

            $delay = $execute($id);
            if ($delay === null) {
                unset($readyAt[$id]);
                $onFinished && $onFinished($id);
            } else {
                $readyAt[$id] = $this->nowMs() + $delay;
            }
        }
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
