<?php

namespace App\Console\Commands;

use App\Evaluation\ModelQuality;
use App\Evaluation\QualityReportBuilder;
use App\Models\Run;
use Illuminate\Console\Command;

class QualityCommand extends Command
{
    protected $signature = 'bench:quality {run : Run id}';

    protected $description = 'Reference accuracy, consistency, judge, human rating and Elo per model';

    public function handle(QualityReportBuilder $builder): int
    {
        $run = Run::query()->find((int) $this->argument('run'));
        if ($run === null) {
            $this->error('Run not found. See bench:runs');

            return self::FAILURE;
        }

        $report = $builder->build($run);
        $num = fn (?float $v, int $p = 2) => $v === null ? '-' : (string) round($v, $p);

        $this->table(
            ['Model', 'n ref', 'MAE', '±1', 'Spearman', 'Bias', "Top-{$report->topK}", 'Repeat σ', 'Δ median', ...array_map(fn ($j) => "Judge {$j}", $report->judges), 'Human', 'Elo'],
            array_map(fn (ModelQuality $q) => [
                $q->model->ref(),
                $q->accuracy->count ?? 0,
                $num($q->accuracy?->mae),
                $q->accuracy ? round($q->accuracy->within1 * 100).'%' : '-',
                $num($q->accuracy?->spearman),
                $num($q->accuracy?->bias),
                $q->accuracy ? round($q->accuracy->topKOverlap * 100).'%' : '-',
                $num($q->repeatSpread),
                $num($q->medianDeviation),
                ...array_map(fn (string $j) => $num($q->judgeScores[$j] ?? null, 1), $report->judges),
                $num($q->humanRating, 1),
                $q->elo ? (string) round($q->elo->rating) : '-',
            ], $report->rows),
        );

        foreach ($report->judgeAgreement as $judge => $agreement) {
            if ($agreement['n'] > 0) {
                $this->line(sprintf('Judge %s vs human: Spearman %s on %d answer(s)', $judge, $num($agreement['rho']), $agreement['n']));
            }
        }

        return self::SUCCESS;
    }
}
