<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ValidatesProviderOption;
use App\Llm\ProviderRegistry;
use App\Models\LlmModel;
use Illuminate\Console\Command;

class ListModelsCommand extends Command
{
    use ValidatesProviderOption;

    protected $signature = 'bench:models:list
        {--provider= : Only this provider}
        {--enabled : Only enabled models}';

    protected $description = 'List stored models';

    public function handle(ProviderRegistry $registry): int
    {
        $provider = $this->providerOption($registry);
        if ($provider === false) {
            return self::FAILURE;
        }

        $models = LlmModel::query()
            ->with('provider')
            ->when($provider, fn ($q) => $q->whereHas('provider', fn ($p) => $p->where('code', $provider)))
            ->when($this->option('enabled'), fn ($q) => $q->where('enabled', true))
            ->orderBy('provider_id')
            ->orderBy('model_id')
            ->get();

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
