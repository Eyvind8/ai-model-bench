<?php

namespace App\Console\Commands;

use App\Models\LlmModel;
use App\Services\Models\ModelManager;
use Illuminate\Console\Command;

class EnableModelsCommand extends Command
{
    protected $signature = 'bench:models:enable
        {patterns* : provider:glob patterns, e.g. groq:qwen/* or *flash*}
        {--disable : Disable instead of enable}';

    protected $description = 'Enable or disable models for runs';

    public function handle(ModelManager $manager): int
    {
        $enabled = ! $this->option('disable');
        $models = $manager->setEnabled(array_values($this->argument('patterns')), $enabled);

        if ($models->isEmpty()) {
            $this->error('No models match. See bench:models:list');

            return self::FAILURE;
        }

        $this->info(sprintf('%s %d model(s):', $enabled ? 'Enabled' : 'Disabled', $models->count()));
        $models->each(fn (LlmModel $m) => $this->line('  '.$m->ref()));

        return self::SUCCESS;
    }
}
