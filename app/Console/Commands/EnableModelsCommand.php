<?php

namespace App\Console\Commands;

use App\Models\LlmModel;
use App\Services\Models\ModelSelector;
use Illuminate\Console\Command;

class EnableModelsCommand extends Command
{
    protected $signature = 'bench:models:enable
        {patterns* : provider:glob patterns, e.g. groq:qwen/* or *flash*}
        {--disable : Disable instead of enable}';

    protected $description = 'Enable or disable models for runs';

    public function handle(ModelSelector $selector): int
    {
        $models = $selector->select(array_values($this->argument('patterns')));
        if ($models->isEmpty()) {
            $this->error('No models match. See bench:models:list');

            return self::FAILURE;
        }

        $enabled = ! $this->option('disable');
        LlmModel::query()->whereKey($models->modelKeys())->update(['enabled' => $enabled]);

        $this->info(sprintf('%s %d model(s):', $enabled ? 'Enabled' : 'Disabled', $models->count()));
        $models->each(fn (LlmModel $m) => $this->line('  '.$m->ref()));

        return self::SUCCESS;
    }
}
