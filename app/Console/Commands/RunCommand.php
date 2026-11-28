<?php

namespace App\Console\Commands;

use App\Models\Result;
use App\Runs\ForecastLine;
use App\Runs\RunForecaster;
use App\Runs\RunLauncher;
use App\Runs\RunPlanner;
use App\Runs\RunRequestFactory;
use Illuminate\Console\Command;
use InvalidArgumentException;

class RunCommand extends Command
{
    protected $signature = 'bench:run
        {--scenario=digest-article : Scenario slug}
        {--dataset= : Dataset id}
        {--input= : Ad-hoc text instead of a dataset}
        {--input-file= : Ad-hoc text from a file}
        {--models=* : provider:glob patterns (default: all enabled models)}
        {--repeats=1 : Repeat every request N times (consistency)}
        {--limit= : Use only the first N dataset items}
        {--sync : Execute inline instead of the queue}
        {--dry-run : Only show the request forecast}';

    protected $description = 'Run a scenario over a dataset (or ad-hoc text) on several models';

    public function handle(RunRequestFactory $factory, RunForecaster $forecaster, RunPlanner $planner, RunLauncher $launcher): int
    {
        try {
            $request = $factory->make(
                (string) $this->option('scenario'),
                $this->option('dataset') !== null ? (int) $this->option('dataset') : null,
                $this->adhocInput(),
                array_values($this->option('models')),
                max(1, (int) $this->option('repeats')),
                $this->option('limit') !== null ? (int) $this->option('limit') : null,
            );
            $this->printForecast($forecaster->forecast($request));

            if ($this->option('dry-run')) {
                return self::SUCCESS;
            }

            $run = $planner->plan($request);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('sync')) {
            $this->info(sprintf('Run #%d: %d request(s) queued. Worker: make up. Progress: bench:report %d', $run->id, $launcher->dispatch($run), $run->id));

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($run->results()->count());
        $launcher->runInline($run, fn (Result $r) => $bar->advance());
        $bar->finish();
        $this->newLine(2);

        return $this->call('bench:report', ['run' => $run->id]);
    }

    private function adhocInput(): ?string
    {
        $file = $this->option('input-file');
        if ($file !== null) {
            return is_readable($file) ? (string) file_get_contents($file) : throw new InvalidArgumentException("Cannot read {$file}");
        }

        return $this->option('input');
    }

    /**
     * @param  list<ForecastLine>  $lines
     */
    private function printForecast(array $lines): void
    {
        $this->table(['Model', 'Requests', 'Left today', 'Fits'], array_map(fn (ForecastLine $l) => [
            $l->model,
            $l->requests,
            $l->exhausted ? 'exhausted' : ($l->remainingToday ?? 'no limit'),
            $l->fits() ? 'yes' : 'NO - extra requests will end as rate_limited',
        ], $lines));
    }
}
