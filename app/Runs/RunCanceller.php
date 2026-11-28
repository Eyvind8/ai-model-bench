<?php

namespace App\Runs;

use App\Enums\ResultStatus;
use App\Enums\RunStatus;
use App\Models\Run;

/**
 * Stops a run: pending results become errors, queued jobs then exit as no-ops.
 */
final class RunCanceller
{
    public function cancel(Run $run): int
    {
        if (in_array($run->status, [RunStatus::Done, RunStatus::Cancelled], true)) {
            return 0;
        }

        $cancelled = $run->results()
            ->where('status', ResultStatus::Pending)
            ->update(['status' => ResultStatus::Error, 'error_message' => 'cancelled']);

        $run->update(['status' => RunStatus::Cancelled, 'finished_at' => now()]);

        return $cancelled;
    }
}
