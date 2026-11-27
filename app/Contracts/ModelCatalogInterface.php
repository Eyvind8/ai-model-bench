<?php

namespace App\Contracts;

use App\Llm\Dto\ModelInfo;
use App\Llm\Exceptions\ProviderException;
use App\Llm\ProviderConfig;

interface ModelCatalogInterface
{
    /**
     * Free chat models currently offered by the provider.
     *
     * @return list<ModelInfo>
     *
     * @throws ProviderException
     */
    public function listModels(ProviderConfig $provider): array;
}
