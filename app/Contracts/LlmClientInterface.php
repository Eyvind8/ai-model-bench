<?php

namespace App\Contracts;

use App\Llm\Dto\ChatRequest;
use App\Llm\Dto\ChatResponse;
use App\Llm\Exceptions\ProviderException;
use App\Llm\ProviderConfig;

interface LlmClientInterface
{
    /**
     * @throws ProviderException
     */
    public function chat(ProviderConfig $provider, ChatRequest $request): ChatResponse;
}
