<?php

namespace Tests\Feature\Runs;

use App\Enums\EvaluationType;
use App\Enums\JsonMode;
use App\Enums\ResultStatus;
use App\Enums\RunStatus;
use App\Jobs\ExecuteResultJob;
use App\Models\Dataset;
use App\Models\Result;
use App\Models\Run;
use App\RateLimit\RetryPolicy;
use App\Runs\Execution\ResultExecutor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\Concerns\LoadsSchema;
use Tests\TestCase;

class RunFlowTest extends TestCase
{
    use LoadsSchema;

    private const CHAT = 'api.groq.com/openai/v1/chat/completions';

    private const ANNOUNCEMENT = 'НБУ знизив облікову ставку до 13%: кредити стануть дешевшими вже навесні.';

    private Dataset $dataset;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake(syncWithCarbon: true);
        config(['bench.retry.jitter_ms' => 0]);
        $this->useTestProviders(['groq' => [
            'limits' => ['rpm' => 30, 'min_interval_ms' => 1000],
            'models' => ['llama-x' => ['json' => false]],
        ]]);
        $this->artisan('bench:models:sync')->assertSuccessful();
        $this->artisan('bench:scenarios:sync')->assertSuccessful();

        $this->dataset = Dataset::query()->create(['name' => 'News']);
        $this->dataset->items()->create(['title' => 'A', 'input' => 'Стаття про облікову ставку НБУ', 'expected_score' => 9, 'meta_json' => ['lang' => 'uk']]);
        $this->dataset->items()->create(['title' => 'B', 'input' => 'Ще одна стаття', 'expected_score' => 4, 'meta_json' => ['lang' => 'uk']]);
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array<string, mixed>
     */
    private function reply(array $answer = ['score' => 7, 'announcement' => self::ANNOUNCEMENT]): array
    {
        return [
            'choices' => [['message' => ['content' => json_encode($answer, JSON_UNESCAPED_UNICODE)], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 30],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function runBench(array $options = []): void
    {
        $this->artisan('bench:run', ['--dataset' => $this->dataset->id, '--models' => ['groq:*'], '--sync' => true, ...$options]);
    }

    private function lastRun(): Run
    {
        return Run::query()->latest('id')->firstOrFail();
    }

    public function test_sync_run_end_to_end(): void
    {
        Http::fake([self::CHAT => Http::response($this->reply())]);

        $this->artisan('bench:run', ['--dataset' => $this->dataset->id, '--models' => ['groq:*'], '--sync' => true])
            ->expectsOutputToContain('groq:qwen/qwen3-32b')
            ->assertSuccessful();

        $run = $this->lastRun();
        $this->assertSame(RunStatus::Done, $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertSame(4, $run->results()->where('status', ResultStatus::Ok)->count());

        $result = $run->results()->whereHas('model', fn ($q) => $q->where('model_id', 'qwen/qwen3-32b'))->firstOrFail();
        $this->assertSame(JsonMode::Native, $result->json_mode);
        $this->assertTrue($result->json_valid);
        $this->assertTrue($result->schema_valid);
        $this->assertSame(7, $result->parsed_json['score']);
        $this->assertSame(30, $result->completion_tokens);

        $metrics = $result->evaluations()->where('type', EvaluationType::Auto)->pluck('value', 'metric')->all();
        $this->assertEquals(['announcement_length_ok' => 1.0, 'score_abs_error' => 2.0, 'language_match' => 1.0], $metrics);

        Http::assertSent(fn (Request $r) => $r['model'] === 'qwen/qwen3-32b' && $r['response_format']['type'] === 'json_schema'
            && str_contains($r['messages'][1]['content'], 'Title: A'));
        Http::assertSent(fn (Request $r) => $r['model'] === 'llama-x' && ! isset($r['response_format']));
        $this->assertSame(JsonMode::Fallback, $run->results()->whereHas('model', fn ($q) => $q->where('model_id', 'llama-x'))->first()->json_mode);
    }

    public function test_report_and_runs_commands(): void
    {
        Http::fake([self::CHAT => Http::response($this->reply())]);
        $this->runBench();

        $this->artisan('bench:report', ['run' => $this->lastRun()->id])
            ->expectsOutputToContain('done')
            ->assertSuccessful();
        $this->artisan('bench:runs')->expectsOutputToContain('digest-article')->assertSuccessful();
        $this->artisan('bench:report', ['run' => 999])->assertFailed();
    }

    public function test_response_format_400_falls_back_to_plain_request(): void
    {
        Http::fake([self::CHAT => function (Request $request) {
            return isset($request['response_format'])
                ? Http::response(['error' => ['message' => 'response_format not supported']], 400)
                : Http::response($this->reply());
        }]);

        $this->runBench(['--models' => ['groq:qwen*'], '--limit' => 1]);

        $result = $this->lastRun()->results()->firstOrFail();
        $this->assertSame(ResultStatus::Ok, $result->status);
        $this->assertSame(JsonMode::Fallback, $result->json_mode);
        $this->assertArrayNotHasKey('response_format', $result->request_json);
        Http::assertSentCount(2);
    }

    public function test_retry_after_429_then_success(): void
    {
        Http::fake([self::CHAT => Http::sequence()
            ->push(['error' => ['message' => 'slow down']], 429, ['Retry-After' => '5'])
            ->push($this->reply())]);

        $this->runBench(['--models' => ['groq:qwen*'], '--limit' => 1]);

        $result = $this->lastRun()->results()->firstOrFail();
        $this->assertSame(ResultStatus::Ok, $result->status);
        $this->assertSame(1, $result->retries);
        Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds >= 5000);
    }

    public function test_persistent_429_marks_model_exhausted_for_the_rest_of_the_run(): void
    {
        config(['bench.retry.max' => 1]);
        $this->app->forgetInstance(RetryPolicy::class);
        Http::fake([self::CHAT => Http::response(['error' => ['message' => 'daily quota']], 429)]);

        $this->runBench(['--models' => ['groq:qwen*']]);

        $results = $this->lastRun()->results()->orderBy('id')->get();
        $this->assertSame([ResultStatus::RateLimited, ResultStatus::RateLimited], $results->pluck('status')->all());
        $this->assertStringContainsString('daily quota', (string) $results[0]->error_message);
        $this->assertStringContainsString('daily limit', (string) $results[1]->error_message);
        // result 2 gets one 429 while result 1 is backing off; after that the model is exhausted
        Http::assertSentCount(3);
        $this->assertSame(RunStatus::Done, $this->lastRun()->status);
    }

    public function test_client_error_is_not_retried(): void
    {
        Http::fake([self::CHAT => Http::response(['error' => ['message' => 'model not found']], 404)]);

        $this->runBench(['--models' => ['groq:llama-x'], '--limit' => 1]);

        $result = $this->lastRun()->results()->firstOrFail();
        $this->assertSame(ResultStatus::Error, $result->status);
        $this->assertSame(404, $result->http_status);
        Http::assertSentCount(1);
    }

    public function test_invalid_and_off_schema_answers(): void
    {
        Http::fake([self::CHAT => Http::sequence()
            ->push(['choices' => [['message' => ['content' => 'I think it is important']]]])
            ->push($this->reply(['score' => 15, 'announcement' => 'x']))]);

        $this->runBench(['--models' => ['groq:qwen*']]);

        [$noJson, $offSchema] = $this->lastRun()->results()->orderBy('id')->get()->all();
        $this->assertSame(ResultStatus::Ok, $noJson->status);
        $this->assertFalse($noJson->json_valid);
        $this->assertNull($noJson->schema_valid);
        $this->assertSame('no JSON object in reply', $noJson->error_message);
        $this->assertTrue($offSchema->json_valid);
        $this->assertFalse($offSchema->schema_valid);
        $this->assertStringContainsString('schema', (string) $offSchema->error_message);
    }

    public function test_queue_mode_dispatches_jobs(): void
    {
        Queue::fake();

        $this->artisan('bench:run', ['--dataset' => $this->dataset->id, '--models' => ['groq:*']])
            ->expectsOutputToContain('4 request(s) queued')
            ->assertSuccessful();

        Queue::assertPushed(ExecuteResultJob::class, 4);
        $this->assertSame(RunStatus::Pending, $this->lastRun()->status);
    }

    public function test_executor_asks_to_wait_on_rate_limit(): void
    {
        $this->freezeTime();
        Queue::fake();
        Http::fake([self::CHAT => Http::response($this->reply())]);
        $this->artisan('bench:run', ['--dataset' => $this->dataset->id, '--models' => ['groq:*']])->assertSuccessful();
        $executor = $this->app->make(ResultExecutor::class);
        [$first, $second] = Result::query()->orderBy('id')->get()->all();

        $this->assertNull($executor->execute($first));
        $this->assertSame(1000, $executor->execute($second));
        $this->assertSame(ResultStatus::Pending, $second->fresh()->status);
        $this->assertSame(RunStatus::Running, $this->lastRun()->status);
    }

    public function test_adhoc_input_and_dry_run(): void
    {
        Http::fake([self::CHAT => Http::response($this->reply())]);

        $this->artisan('bench:run', ['--input' => 'Текст', '--models' => ['groq:qwen*'], '--dry-run' => true])
            ->expectsOutputToContain('Left today')
            ->assertSuccessful();
        $this->assertSame(0, Run::query()->count());

        $this->artisan('bench:run', ['--input' => 'Текст', '--models' => ['groq:qwen*'], '--repeats' => 2, '--sync' => true])->assertSuccessful();
        $this->assertSame(2, $this->lastRun()->results()->where('status', ResultStatus::Ok)->count());
        $this->assertSame([1, 2], $this->lastRun()->results()->orderBy('attempt_no')->pluck('attempt_no')->all());
    }

    public function test_input_errors(): void
    {
        $this->artisan('bench:run', ['--models' => ['groq:*']])->expectsOutputToContain('exactly one')->assertFailed();
        $this->artisan('bench:run', ['--scenario' => 'nope', '--input' => 'x'])->expectsOutputToContain('not found')->assertFailed();
        $this->artisan('bench:run', ['--input' => 'x', '--models' => ['none:*']])->expectsOutputToContain('No models')->assertFailed();
        $this->artisan('bench:run', ['--dataset' => Dataset::query()->create(['name' => 'empty'])->id])->expectsOutputToContain('Nothing to send')->assertFailed();
    }

    public function test_limits_command(): void
    {
        $this->artisan('bench:limits', ['--provider' => 'groq'])
            ->expectsOutputToContain('groq:qwen/qwen3-32b')
            ->assertSuccessful();
    }

    public function test_default_models_skip_providers_without_key(): void
    {
        Queue::fake();

        $this->artisan('bench:run', ['--dataset' => $this->dataset->id])->assertSuccessful();

        $models = $this->lastRun()->models()->with('provider')->get()->map(fn ($m) => $m->provider->code)->unique()->all();
        $this->assertSame(['groq'], array_values($models));
    }
}
