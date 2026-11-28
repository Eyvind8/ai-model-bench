<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsBench;
use Tests\TestCase;

class ModelsAndScenariosPagesTest extends TestCase
{
    use SeedsBench;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBench();
    }

    public function test_dashboard(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Groq')
            ->assertSee('GEMINI_API_KEY is not set')
            ->assertSee('News');
    }

    public function test_models_page_lists_models_and_provider_state(): void
    {
        $this->get('/models')
            ->assertOk()
            ->assertSee('qwen/qwen3-32b')
            ->assertSee('gemini-3.5-flash-lite')
            ->assertSee('GEMINI_API_KEY is not set');
    }

    public function test_toggle_model(): void
    {
        $this->post("/models/{$this->qwen()->id}/toggle")->assertRedirect('/models');
        $this->assertFalse($this->qwen()->enabled);

        $this->post("/models/{$this->qwen()->id}/toggle")->assertRedirect('/models');
        $this->assertTrue($this->qwen()->enabled);
    }

    public function test_sync_models_from_ui(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->jsonFixture('catalogs/groq.json'))]);

        $this->post('/models/sync', ['discover' => '1'])
            ->assertRedirect('/models')
            ->assertSessionHas('status');

        $this->get('/models')->assertSee('meta-llama/llama-4-scout-17b-16e-instruct');
    }

    public function test_ping_model_returns_json(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->chatReply())]);

        $this->postJson("/models/{$this->qwen()->id}/ping")
            ->assertOk()
            ->assertJson(['status' => 'ok', 'label' => 'ok']);
    }

    public function test_scenarios_pages(): void
    {
        $this->get('/scenarios')->assertOk()->assertSee('digest-article');
        $this->get("/scenarios/{$this->digest()->id}")
            ->assertOk()
            ->assertSee('editor of a news digest')
            ->assertSee('announcement_length_ok');
        $this->post('/scenarios/sync')->assertRedirect('/scenarios')->assertSessionHas('status');
    }
}
