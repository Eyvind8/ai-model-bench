<?php

namespace App\Runs;

use App\Enums\ResultStatus;
use App\Enums\RunStatus;
use App\Models\Run;

/**
 * Run lifecycle: pending -> running (first execution) -> done (no pending results).
 */
final class RunProgress
{
    public function markStarted(Run $run): void
    {
        if ($run->status === RunStatus::Pending) {
            $run->update(['status' => RunStatus::Running, 'started_at' => now()]);
        }
    }

    public function refresh(Run $run): void
    {
        if ($run->status === RunStatus::Running && ! $run->results()->where('status', ResultStatus::Pending)->exists()) {
            $run->update(['status' => RunStatus::Done, 'finished_at' => now()]);
        }
    }

    /**
     * @return array<string, int> result status => count
     */
    public function counts(Run $run): array
    {
        $counts = array_fill_keys(array_map(fn (ResultStatus $s) => $s->value, ResultStatus::cases()), 0);

        foreach ($run->results()->toBase()->selectRaw('status, count(*) as c')->groupBy('status')->get() as $row) {
            $counts[$row->status] = (int) $row->c;
        }

        return $counts;
    }
}
