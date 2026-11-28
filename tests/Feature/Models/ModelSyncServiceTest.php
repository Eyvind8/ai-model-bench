<?php

namespace Tests\Feature\Models;

use App\Enums\ModelSource;
use App\Enums\SyncStatus;
use App\Models\LlmModel;
use App\Models\Provider;
use App\Services\Models\ModelSyncService;
use App\Services\Models\SyncReport;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\LoadsSchema;
use Tests\TestCase;

class ModelSyncServiceTest extends TestCase
{
    use LoadsSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useTestProviders();
    }

    /**
     * @return array<string, SyncReport>
     */
    private function sync(?string $provider = null, bool $discover = false): array
    {
        $reports = [];
        foreach ($this->app->make(ModelSyncService::class)->sync($provider, $discover) as $report) {
            $reports[$report->provider] = $report;
        }

        return $reports;
    }

    private function model(string $id): LlmModel
    {
        return LlmModel::query()->where('model_id', $id)->firstOrFail();
    }

    public function test_config_models_are_stored_without_http_calls(): void
    {
        Http::fake();

        $reports = $this->sync();

        Http::assertNothingSent();
        $this->assertSame(SyncStatus::Ok, $reports['groq']->status);
        $this->assertSame(1, $reports['groq']->counts->added);
        $this->assertSame('GEMINI_API_KEY is not set', $reports['gemini']->message);
        $this->assertSame(['gemini', 'groq'], Provider::query()->orderBy('code')->pluck('code')->all());

        $qwen = $this->model('qwen/qwen3-32b');
        $this->assertSame(ModelSource::Config, $qwen->source);
        $this->assertTrue($qwen->enabled);
        $this->assertTrue($qwen->supports_json);
        $this->assertSame('Qwen3 32B', $qwen->display_name);
    }

    public function test_model_removed_from_config_becomes_unavailable(): void
    {
        $this->sync();
        config(['bench.providers.groq.models' => ['new/model' => []]]);

        $reports = $this->sync('groq');

        $this->assertSame(1, $reports['groq']->counts->added);
        $this->assertSame(1, $reports['groq']->counts->removed);
        $this->assertFalse($this->model('qwen/qwen3-32b')->available);
        $this->assertTrue($this->model('new/model')->available);
    }

    public function test_resync_keeps_user_enabled_flag(): void
    {
        $this->sync();
        $this->model('qwen/qwen3-32b')->update(['enabled' => false]);

        $this->sync();

        $this->assertFalse($this->model('qwen/qwen3-32b')->enabled);
    }

    public function test_discover_adds_catalog_models_disabled_and_keeps_config_models(): void
    {
        Http::fake(['api.groq.com/openai/v1/models' => Http::response($this->jsonFixture('catalogs/groq.json'))]);

        $reports = $this->sync('groq', discover: true);

        $this->assertSame(SyncStatus::Ok, $reports['groq']->status);
        $this->assertSame(2, $reports['groq']->counts->added);

        $scout = $this->model('meta-llama/llama-4-scout-17b-16e-instruct');
        $this->assertSame(ModelSource::Catalog, $scout->source);
        $this->assertFalse($scout->enabled);
        $this->assertSame(131072, $scout->context_window);

        $qwen = $this->model('qwen/qwen3-32b');
        $this->assertSame(ModelSource::Config, $qwen->source);
        $this->assertSame('Qwen3 32B', $qwen->display_name);
    }

    public function test_catalog_model_missing_on_next_discover_becomes_unavailable_and_returns(): void
    {
        Http::fakeSequence('api.groq.com/*')
            ->push(['data' => [['id' => 'a']]])
            ->push(['data' => []])
            ->push(['data' => [['id' => 'a']]]);

        $this->sync('groq', true);
        $this->sync('groq', true);
        $this->assertFalse($this->model('a')->available);

        $this->sync('groq', true);
        $this->assertTrue($this->model('a')->available);
    }

    public function test_discover_skips_unconfigured_provider(): void
    {
        Http::fake();

        $reports = $this->sync('gemini', true);

        $this->assertSame(SyncStatus::Skipped, $reports['gemini']->status);
        $this->assertSame(1, $reports['gemini']->counts->added);
        Http::assertNothingSent();
    }

    public function test_discover_without_catalog_driver_uses_config_only(): void
    {
        config(['bench.providers.groq.catalog' => null]);
        Http::fake();

        $reports = $this->sync('groq', true);

        $this->assertSame(SyncStatus::Ok, $reports['groq']->status);
        $this->assertStringContainsString('no catalog', (string) $reports['groq']->message);
        Http::assertNothingSent();
    }

    public function test_discover_error_is_reported_as_failed(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['error' => 'invalid api key'], 401)]);

        $reports = $this->sync('groq', true);

        $this->assertSame(SyncStatus::Failed, $reports['groq']->status);
        $this->assertStringContainsString('401', (string) $reports['groq']->message);
        $this->assertSame(1, LlmModel::query()->count());
    }

    public function test_model_dropped_from_config_is_taken_over_by_catalog(): void
    {
        $this->sync();
        config(['bench.providers.groq.models' => []]);
        Http::fake(['api.groq.com/*' => Http::response(['data' => [['id' => 'qwen/qwen3-32b']]])]);

        $this->sync('groq', true);

        $qwen = $this->model('qwen/qwen3-32b');
        $this->assertTrue($qwen->available);
        $this->assertSame(ModelSource::Catalog, $qwen->source);
    }

    public function test_unknown_catalog_driver_is_reported_without_crash(): void
    {
        config(['bench.providers.groq.catalog' => 'typo']);
        Http::fake();

        $reports = $this->sync(discover: true);

        $this->assertSame(SyncStatus::Failed, $reports['groq']->status);
        $this->assertStringContainsString('typo', (string) $reports['groq']->message);
        $this->assertArrayHasKey('gemini', $reports);
    }
}
