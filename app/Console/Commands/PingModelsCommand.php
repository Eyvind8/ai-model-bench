<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ValidatesProviderOption;
use App\Enums\PingStatus;
use App\Llm\Exceptions\UnknownProviderException;
use App\Llm\ProviderRegistry;
use App\Services\Ping\ModelPinger;
use App\Services\Ping\PingTargetResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PingModelsCommand extends Command
{
    use ValidatesProviderOption;

    protected $signature = 'bench:models:ping
        {--provider= : Only enabled models of this provider}
        {--model= : Ping one model by provider:model, even if it is not stored}';

    protected $description = 'Send a tiny request to models to check keys, model IDs and availability';

    public function handle(PingTargetResolver $resolver, ModelPinger $pinger, ProviderRegistry $registry): int
    {
        $provider = $this->providerOption($registry);
        if ($provider === false) {
            return self::FAILURE;
        }

        try {
            $targets = $resolver->resolve($provider, $this->option('model'));
        } catch (InvalidArgumentException|UnknownProviderException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($targets === []) {
            $this->warn('No enabled models. Run bench:models:sync, then bench:models:enable <pattern>.');

            return self::FAILURE;
        }

        $ok = true;
        $rows = [];
        foreach ($pinger->pingAll($targets) as $result) {
            $ok = $ok && $result->status === PingStatus::Ok;
            $rows[] = [
                $result->target->provider->code.':'.$result->target->modelId,
                $result->status->label(),
                $result->latencyMs !== null ? $result->latencyMs.' ms' : '',
                Str::limit(trim((string) ($result->reply ?? $result->error)), 80),
            ];
        }

        $this->table(['Model', 'Status', 'Latency', 'Reply / error'], $rows);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
