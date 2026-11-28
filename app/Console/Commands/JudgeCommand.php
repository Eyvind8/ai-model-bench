<?php

namespace App\Console\Commands;

use App\Evaluation\Judge\JudgeService;
use App\Models\Run;
use App\Services\Models\ModelSelector;
use Illuminate\Console\Command;

class JudgeCommand extends Command
{
    protected $signature = 'bench:judge
        {run : Run id}
        {--judge= : Judge model as provider:model}
        {--sync : Execute inline instead of the queue}';

    protected $description = 'Rate the answers of a run with an LLM judge (the judge skips its own answers)';

    public function handle(JudgeService $judges, ModelSelector $selector): int
    {
        $run = Run::query()->find((int) $this->argument('run'));
        if ($run === null) {
            $this->error('Run not found. See bench:runs');

            return self::FAILURE;
        }

        $matches = $this->option('judge') ? $selector->select([(string) $this->option('judge')]) : collect();
        if ($matches->count() !== 1) {
            $this->error(sprintf('--judge must match exactly one stored model, matched %d (see bench:models:list)', $matches->count()));

            return self::FAILURE;
        }
        $judge = $matches->first();

        $count = $this->option('sync') ? $judges->runInline($run, $judge) : $judges->dispatch($run, $judge);
        $this->info(sprintf('%s: %d answer(s) %s', $judge->ref(), $count, $this->option('sync') ? 'judged' : 'queued'));

        return self::SUCCESS;
    }
}
