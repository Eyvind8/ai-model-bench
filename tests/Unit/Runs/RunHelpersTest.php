<?php

namespace Tests\Unit\Runs;

use App\Enums\ResponseFormat;
use App\Llm\Dto\ChatRequest;
use App\Models\DatasetItem;
use App\Runs\PromptRenderer;
use App\Runs\RunInput;
use App\Support\Stats;
use PHPUnit\Framework\TestCase;

class RunHelpersTest extends TestCase
{
    public function test_prompt_renderer_placeholders(): void
    {
        $item = new DatasetItem;
        $item->setRawAttributes(['title' => 'T', 'source_url' => 'https://n.test/a', 'meta_json' => '{"site":"n.test","tags":["x"]}']);

        $text = (new PromptRenderer)->render('{{title}}|{{ input }}|{{url}}|{{meta.site}}|{{meta.tags}}|{{unknown}}', new RunInput('body', $item));

        $this->assertSame('T|body|https://n.test/a|n.test||', $text);
    }

    public function test_prompt_renderer_adhoc(): void
    {
        $this->assertSame('Title: \nX', (new PromptRenderer)->render('Title: {{title}}\n{{input}}', new RunInput('X')));
    }

    public function test_chat_request_payload_roundtrip(): void
    {
        $request = new ChatRequest('m', [['role' => 'user', 'content' => 'hi']], 0.2, 300, ResponseFormat::JsonSchema, ['type' => 'object']);

        $copy = ChatRequest::fromPayload($request->toPayload());

        $this->assertEquals($request, $copy);
        $this->assertSame(ResponseFormat::Text, $copy->withoutResponseFormat()->responseFormat);
        $this->assertArrayNotHasKey('response_format', $copy->withoutResponseFormat()->toPayload());
    }

    public function test_stats(): void
    {
        $this->assertSame(20.0, Stats::percentile([50, 10, 20, 40, 30], 40));
        $this->assertSame(50.0, Stats::percentile([50, 10, 20, 40, 30], 95));
        $this->assertNull(Stats::percentile([], 50));
        $this->assertSame(2.0, Stats::mean([1, 2, 3]));
        $this->assertSame(0.5, Stats::rate([true, false, null]));
        $this->assertNull(Stats::rate([null]));
    }
}
