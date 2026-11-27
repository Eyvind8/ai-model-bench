<?php

namespace Tests\Unit\Llm;

use App\Enums\ResponseFormat;
use App\Llm\Dto\ChatRequest;
use PHPUnit\Framework\TestCase;

class ChatRequestTest extends TestCase
{
    public function test_text_payload_omits_optional_fields(): void
    {
        $request = new ChatRequest('m-1', [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame([
            'model' => 'm-1',
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ], $request->toPayload());
    }

    public function test_payload_includes_sampling_params(): void
    {
        $request = new ChatRequest('m-1', [], temperature: 0.2, maxTokens: 300);

        $payload = $request->toPayload();

        $this->assertSame(0.2, $payload['temperature']);
        $this->assertSame(300, $payload['max_tokens']);
    }

    public function test_json_object_format(): void
    {
        $request = new ChatRequest('m-1', [], responseFormat: ResponseFormat::JsonObject);

        $this->assertSame(['type' => 'json_object'], $request->toPayload()['response_format']);
    }

    public function test_json_schema_format(): void
    {
        $schema = ['type' => 'object', 'properties' => ['score' => ['type' => 'integer']]];
        $request = new ChatRequest('m-1', [], responseFormat: ResponseFormat::JsonSchema, jsonSchema: $schema);

        $this->assertSame([
            'type' => 'json_schema',
            'json_schema' => ['name' => 'response', 'strict' => true, 'schema' => $schema],
        ], $request->toPayload()['response_format']);
    }

    public function test_json_schema_without_schema_falls_back_to_json_object(): void
    {
        $request = new ChatRequest('m-1', [], responseFormat: ResponseFormat::JsonSchema);

        $this->assertSame(['type' => 'json_object'], $request->toPayload()['response_format']);
    }
}
