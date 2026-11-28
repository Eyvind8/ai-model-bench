<?php

namespace App\Console\Commands;

use App\Models\Run;
use App\Runs\Report\ModelReport;
use App\Runs\Report\RunReportBuilder;
use App\Runs\RunProgress;
use Illuminate\Console\Command;

class ReportCommand extends Command
{
    protected $signature = 'bench:report {run : Run id}';

    protected $description = 'Per-model summary of a run';

    public function handle(RunReportBuilder $builder, RunProgress $progress): int
    {
        $run = Run::query()->find((int) $this->argument('run'));
        if ($run === null) {
            $this->error('Run not found. See bench:runs');

            return self::FAILURE;
        }

        $counts = $progress->counts($run);
        $this->info(sprintf(
            'Run #%d · %s · %s · %s',
            $run->id,
            $run->definition()->slug,
            $run->status->value,
            implode(', ', array_map(fn ($status, $count) => "{$status} {$count}", array_keys($counts), $counts)),
        ));

        $reports = $builder->build($run);
        $labels = array_keys($run->definition()->metrics);
        $this->table(
            ['Model', 'OK/Total', 'Err', 'JSON', 'Schema', 'p50 ms', 'p95 ms', 'Tokens', ...$labels],
            array_map(fn (ModelReport $r) => $this->row($r, $labels), $reports),
        );

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $labels
     * @return list<string>
     */
    private function row(ModelReport $r, array $labels): array
    {
        return [
            $r->model,
            ($r->statuses['ok'] ?? 0).'/'.$r->total,
            $this->percent($r->errorRate()),
            $this->percent($r->jsonValidRate),
            $this->percent($r->schemaValidRate),
            $this->number($r->latencyP50, 0),
            $this->number($r->latencyP95, 0),
            $this->number($r->avgCompletionTokens, 0),
            ...array_map(fn (string $label) => $this->number($r->metrics[$label] ?? null, 2), $labels),
        ];
    }

    private function percent(?float $value): string
    {
        return $value === null ? '-' : round($value * 100).'%';
    }

    private function number(?float $value, int $precision): string
    {
        return $value === null ? '-' : (string) round($value, $precision);
    }
}
