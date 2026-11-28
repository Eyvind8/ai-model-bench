<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ValidatesProviderOption;
use App\Llm\ProviderRegistry;
use App\Models\LlmModel;
use App\Services\Models\ModelManager;
use Illuminate\Console\Command;

class ListModelsCommand extends Command
{
    use ValidatesProviderOption;

    protected $signature = 'bench:models:list
        {--provider= : Only this provider}
        {--enabled : Only enabled models}';

    protected $description = 'List stored models';

    public function handle(ProviderRegistry $registry, ModelManager $manager): int
    {
        $provider = $this->providerOption($registry);
        if ($provider === false) {
            return self::FAILURE;
        }

        $models = $manager->list($provider, (bool) $this->option('enabled'));

        $this->table(
            ['Ref', 'Name', 'Context', 'JSON', 'Source', 'Enabled', 'Available'],
            $models->map(fn (LlmModel $m) => [
                $m->ref(),
                $m->display_name,
                $m->context_window,
                $this->flag($m->supports_json),
                $m->source->value,
                $this->flag($m->enabled),
                $this->flag($m->available),
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function flag(?bool $value): string
    {
        return match ($value) {
            true => 'yes',
            false => 'no',
            null => '?',
        };
    }
}
