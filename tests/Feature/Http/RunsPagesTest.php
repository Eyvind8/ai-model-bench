<?php

namespace Tests\Feature\Http;

use App\Enums\EvaluationType;
use App\Enums\ResultStatus;
use App\Enums\RunStatus;
use App\Jobs\ExecuteResultJob;
use App\Models\Result;
use App\Models\Run;
use App\Runs\RunLauncher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\SeedsBench;
use Tests\TestCase;

class RunsPagesTest extends TestCase
{
    use SeedsBench;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBench();
        Queue::fake();
    }

    private function lastRun(): Run
    {
        return Run::query()->latest('id')->firstOrFail();
    }

    private function executedRun(): Run
    {
        Http::fake(['api.groq.com/*' => Http::response($this->chatReply())]);
        $this->post('/runs', [
            'scenario_id' => $this->digest()->id,
            'dataset_id' => $this->dataset->id,
            'model_ids' => [$this->qwen()->id],
            'repeats' => 2,
        ]);
        $run = $this->lastRun();
        $this->app->make(RunLauncher::class)->runInline($run);

        return $run->refresh();
    }

    public function test_create_page(): void
    {
        $this->get('/runs/create')
            ->assertOk()
            ->assertSee('digest-article')
            ->assertSee('qwen/qwen3-32b')
            ->assertSee('News');
    }

    public function test_forecast(): void
    {
        $this->postJson('/runs/forecast', [
            'scenario_id' => $this->digest()->id,
            'dataset_id' => $this->dataset->id,
            'model_ids' => [$this->qwen()->id],
            'repeats' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('lines.0.model', 'groq:qwen/qwen3-32b')
            ->assertJsonPath('lines.0.requests', 6)
            ->assertJsonPath('lines.0.fits', true);
    }

    public function test_store_dataset_run_dispatches_jobs(): void
    {
        $response = $this->post('/runs', [
            'scenario_id' => $this->digest()->id,
            'dataset_id' => $this->dataset->id,
            'model_ids' => [$this->qwen()->id],
            'repeats' => 1,
        ]);

        $response->assertRedirect("/runs/{$this->lastRun()->id}");
        Queue::assertPushed(ExecuteResultJob::class, 2);
    }

    public function test_playground_creates_adhoc_run(): void
    {
        $this->get('/playground')->assertOk()->assertSee('qwen/qwen3-32b');

        $this->post('/runs', [
            'scenario_id' => $this->digest()->id,
            'input' => 'Довільний текст',
            'model_ids' => [$this->qwen()->id],
        ])->assertRedirect();

        $this->assertSame('Довільний текст', $this->lastRun()->adhoc_input);
        Queue::assertPushed(ExecuteResultJob::class, 1);
    }

    public function test_store_validation(): void
    {
        $this->post('/runs', ['scenario_id' => $this->digest()->id, 'model_ids' => []])
            ->assertSessionHasErrors(['model_ids', 'input']);
        $this->post('/runs', [
            'scenario_id' => $this->digest()->id,
            'dataset_id' => $this->dataset->id,
            'input' => 'both',
            'model_ids' => [$this->qwen()->id],
        ])->assertSessionHasErrors('input');
    }

    public function test_run_page_shows_report_heatmap_and_answers(): void
    {
        $run = $this->executedRun();
        $this->assertSame(RunStatus::Done, $run->status);

        $this->get("/runs/{$run->id}")
            ->assertOk()
            ->assertSee('groq:qwen/qwen3-32b')
            ->assertSee('Rate cut')
            ->assertSee('НБУ знизив ставку')
            ->assertSee('announcement_length_ok');

        $this->get('/runs')->assertOk()->assertSee('digest-article');
    }

    public function test_progress_json(): void
    {
        $this->post('/runs', ['scenario_id' => $this->digest()->id, 'input' => 'x', 'model_ids' => [$this->qwen()->id]]);

        $this->getJson("/runs/{$this->lastRun()->id}/progress")
            ->assertOk()
            ->assertJson(['status' => 'pending', 'finished' => false, 'counts' => ['pending' => 1]]);
    }

    public function test_cancel_marks_pending_results(): void
    {
        $this->post('/runs', ['scenario_id' => $this->digest()->id, 'input' => 'x', 'model_ids' => [$this->qwen()->id]]);
        $run = $this->lastRun();

        $this->post("/runs/{$run->id}/cancel")->assertRedirect("/runs/{$run->id}");

        $this->assertSame(RunStatus::Cancelled, $run->fresh()->status);
        $this->assertSame(ResultStatus::Error, $run->results()->first()->status);
        $this->assertSame('cancelled', $run->results()->first()->error_message);
    }

    public function test_export_csv(): void
    {
        $run = $this->executedRun();

        $response = $this->get("/runs/{$run->id}/export");

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('model,item_id,title,attempt,status', $csv);
        $this->assertStringContainsString('groq:qwen/qwen3-32b', $csv);
        $this->assertSame(5, substr_count(trim($csv), "\n") + 1);
    }

    public function test_result_page_and_human_rating(): void
    {
        $run = $this->executedRun();
        $result = Result::query()->where('run_id', $run->id)->firstOrFail();

        $this->get("/results/{$result->id}")
            ->assertOk()
            ->assertSee('response_format')
            ->assertSee('НБУ знизив ставку');

        $this->post("/results/{$result->id}/rate", ['rating' => 4, 'comment' => 'ok'])->assertRedirect();
        $this->post("/results/{$result->id}/rate", ['rating' => 5])->assertRedirect();

        $human = $result->evaluations()->where('type', EvaluationType::Human)->get();
        $this->assertCount(1, $human);
        $this->assertSame(5.0, $human->first()->value);

        $this->post("/results/{$result->id}/rate", ['rating' => 9])->assertSessionHasErrors('rating');
    }
}
