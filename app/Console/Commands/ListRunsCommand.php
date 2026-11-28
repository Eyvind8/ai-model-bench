<?php

namespace App\Console\Commands;

use App\Models\Run;
use App\Runs\RunProgress;
use Illuminate\Console\Command;

class ListRunsCommand extends Command
{
    protected $signature = 'bench:runs {--limit=20}';

    protected $description = 'List recent runs';

    public function handle(RunProgress $progress): int
    {
        $runs = Run::query()->with('dataset')->latest('id')->limit((int) $this->option('limit'))->get();

        $this->table(['ID', 'Scenario', 'Input', 'Status', 'OK', 'Pending', 'Errors', 'Created'], $runs->map(function (Run $run) use ($progress) {
            $counts = $progress->counts($run);

            return [
                $run->id,
                $run->definition()->slug,
                $run->dataset ? "dataset #{$run->dataset->id} {$run->dataset->name}" : 'ad-hoc',
                $run->status->value,
                $counts['ok'],
                $counts['pending'],
                $counts['error'] + $counts['rate_limited'],
                $run->created_at->format('Y-m-d H:i'),
            ];
        })->all());

        return self::SUCCESS;
    }
}
