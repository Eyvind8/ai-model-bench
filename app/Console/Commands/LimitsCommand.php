<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ValidatesProviderOption;
use App\Contracts\RateLimiterInterface;
use App\Llm\ProviderRegistry;
use App\Models\LlmModel;
use App\Services\Models\ModelManager;
use Illuminate\Console\Command;

class LimitsCommand extends Command
{
    use ValidatesProviderOption;

    protected $signature = 'bench:limits {--provider= : Only this provider}';

    protected $description = 'Remaining free-tier quota of enabled models (local counters)';

    public function handle(ProviderRegistry $registry, ModelManager $manager, RateLimiterInterface $limiter): int
    {
        $provider = $this->providerOption($registry);
        if ($provider === false) {
            return self::FAILURE;
        }

        $models = $manager->list($provider, onlyEnabled: true)
            ->filter(fn (LlmModel $m) => $m->available && $registry->has($m->provider->code));

        $this->table(['Model', 'RPM left', 'RPD left', 'TPM left', 'State'], $models->map(function (LlmModel $m) use ($registry, $limiter) {
            $quota = $limiter->remaining($registry->get($m->provider->code), $m->model_id);

            return [
                $m->ref(),
                $quota->rpm ?? '∞',
                $quota->rpd ?? '∞',
                $quota->tpm ?? '∞',
                $quota->exhausted ? 'exhausted until 00:00 UTC' : 'ok',
            ];
        })->all());

        return self::SUCCESS;
    }
}
