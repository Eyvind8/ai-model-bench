<?php

namespace Tests\Feature\Evaluation;

use App\Enums\EvaluationType;
use App\Enums\VoteWinner;
use App\Evaluation\Arena\ArenaService;
use App\Evaluation\Judge\JudgeExecutor;
use App\Evaluation\QualityReportBuilder;
use App\Jobs\JudgeResultJob;
use App\Models\Evaluation;
use App\Models\LlmModel;
use App\Models\PairwiseVote;
use App\Models\Result;
use App\Models\Run;
use App\Runs\RunLauncher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\Concerns\SeedsBench;
use Tests\TestCase;

class QualityFlowTest extends TestCase
{
    use SeedsBench;

    private Run $run;

    /** Judge reply override; null = valid verdict. */
    private ?string $judgeContent = null;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake(syncWithCarbon: true);
        $this->seedBench();
        $this->useTestProviders(['groq' => [
            'limits' => ['rpm' => 1000, 'min_interval_ms' => 0],
            'models' => ['llama-x' => ['json' => false]],
        ]]);
        $this->artisan('bench:models:sync')->assertSuccessful();

        $answers = ['qwen/qwen3-32b' => [8, 9, 9, 8], 'llama-x' => [5, 5, 5, 5]];
        Http::fake(['api.groq.com/*' => function (Request $request) use (&$answers) {
            if (str_contains($request['messages'][0]['content'], 'strict evaluator')) {
                return Http::response(['choices' => [['message' => ['content' => $this->judgeContent
                    ?? '{"scores": {"accuracy": 8, "informativeness": 6}, "overall": 7, "comment": "fine"}']]]]);
            }

            return Http::response($this->chatReply(array_shift($answers[$request['model']])));
        }]);

        $this->post('/runs', [
            'scenario_id' => $this->digest()->id,
            'dataset_id' => $this->dataset->id,
            'model_ids' => [$this->qwen()->id, $this->llama()->id],
            'repeats' => 2,
        ]);
        $this->run = Run::query()->latest('id')->firstOrFail();
        $this->app->make(RunLauncher::class)->runInline($this->run);
    }

    private function llama(): LlmModel
    {
        return LlmModel::query()->where('model_id', 'llama-x')->firstOrFail();
    }

    /**
     * @return Collection<int, Evaluation>
     */
    private function judgeEvaluations(): Collection
    {
        return Evaluation::query()->where('type', EvaluationType::Judge)->get();
    }

    public function test_quality_report_reference_accuracy_and_consistency(): void
    {
        $report = $this->app->make(QualityReportBuilder::class)->build($this->run);
        $rows = collect($report->rows)->keyBy(fn ($row) => $row->model->model_id);

        $qwen = $rows['qwen/qwen3-32b'];
        $this->assertSame(1, $qwen->accuracy?->count);                   // only "Rate cut" has a reference
        $this->assertEqualsWithDelta(0.5, $qwen->accuracy->mae, 1e-9);   // mean(8, 9) vs 9
        $this->assertEqualsWithDelta(0.5, $qwen->repeatSpread, 1e-9);   // std([8,9]) on both items
        $this->assertEqualsWithDelta(0.0, $rows['llama-x']->repeatSpread, 1e-9);
        $this->assertEqualsWithDelta(1.75, $qwen->medianDeviation, 1e-9); // |8.5-6.75|

        $this->get("/runs/{$this->run->id}")->assertOk()->assertSee('Якість')->assertSee('MAE');
        $this->artisan('bench:quality', ['run' => $this->run->id])->expectsOutputToContain('groq:qwen/qwen3-32b')->assertSuccessful();
    }

    public function test_judge_command_scores_other_models_only_and_is_idempotent(): void
    {
        $this->artisan('bench:judge', ['run' => $this->run->id, '--judge' => 'groq:llama-x', '--sync' => true])
            ->expectsOutputToContain('4')
            ->assertSuccessful();

        $judged = $this->judgeEvaluations();
        $overall = $judged->where('metric', 'judge_overall');
        $this->assertCount(4, $overall);
        $this->assertSame([7.0], $overall->pluck('value')->unique()->values()->all());
        $this->assertSame(8.0, $judged->firstWhere('metric', 'judge:accuracy')->value);
        $this->assertSame($this->llama()->id, $overall->first()->judge_model_id);
        $this->assertSame([$this->qwen()->id], Result::query()->whereIn('id', $overall->pluck('result_id'))->pluck('model_id')->unique()->values()->all());

        Http::assertSent(fn (Request $r) => str_contains($r['messages'][0]['content'], 'strict evaluator')
            && ! str_contains($r['messages'][1]['content'], 'qwen'));

        $sent = count(Http::recorded());
        $this->artisan('bench:judge', ['run' => $this->run->id, '--judge' => 'groq:llama-x', '--sync' => true])
            ->expectsOutputToContain('0')
            ->assertSuccessful();
        $this->assertCount($sent, Http::recorded());

        $this->get("/runs/{$this->run->id}")->assertOk()->assertSee('7');
    }

    public function test_invalid_judge_reply_is_recorded_as_error(): void
    {
        $this->judgeContent = 'I like it';

        $this->artisan('bench:judge', ['run' => $this->run->id, '--judge' => 'groq:llama-x', '--sync' => true])->assertSuccessful();

        $this->assertCount(0, $this->judgeEvaluations()->where('metric', 'judge_overall'));
        $this->assertCount(4, $this->judgeEvaluations()->where('metric', 'judge_error'));
    }

    public function test_judge_command_validation(): void
    {
        $this->artisan('bench:judge', ['run' => $this->run->id])->assertFailed();
        $this->artisan('bench:judge', ['run' => $this->run->id, '--judge' => 'groq:nope'])->assertFailed();
        $this->artisan('bench:judge', ['run' => 999, '--judge' => 'groq:llama-x'])->assertFailed();
    }

    public function test_judge_from_ui_queues_jobs(): void
    {
        Queue::fake();

        $this->post("/runs/{$this->run->id}/judge", ['judge_model_id' => $this->llama()->id])
            ->assertRedirect("/runs/{$this->run->id}")
            ->assertSessionHas('status');

        Queue::assertPushed(JudgeResultJob::class, 4);
        $this->post("/runs/{$this->run->id}/judge", [])->assertSessionHasErrors('judge_model_id');
    }

    public function test_judge_agreement_with_human_ratings(): void
    {
        $this->artisan('bench:judge', ['run' => $this->run->id, '--judge' => 'groq:llama-x', '--sync' => true])->assertSuccessful();
        $judgedIds = $this->judgeEvaluations()->where('metric', 'judge_overall')->pluck('result_id')->all();
        Evaluation::query()->where('metric', 'judge_overall')->whereIn('result_id', $judgedIds)->get()
            ->each(fn (Evaluation $e, int $i) => $e->update(['value' => [3, 5, 7, 9][$i]]));
        foreach ($judgedIds as $i => $id) {
            $this->post("/results/{$id}/rate", ['rating' => [1, 2, 4, 5][$i]]);
        }

        $report = $this->app->make(QualityReportBuilder::class)->build($this->run);

        $this->assertSame(4, $report->judgeAgreement['groq:llama-x']['n']);
        $this->assertEqualsWithDelta(1.0, $report->judgeAgreement['groq:llama-x']['rho'], 1e-9);
    }

    public function test_arena_blind_vote_and_leaderboard(): void
    {
        $page = $this->get("/runs/{$this->run->id}/arena")->assertOk()->assertSee('Відповідь A')->assertSee('Відповідь B');
        $this->assertStringNotContainsString('qwen/qwen3-32b', strip_tags($page->getContent()));

        preg_match('/name="result_a" value="(\d+)"/', $page->getContent(), $a);
        preg_match('/name="result_b" value="(\d+)"/', $page->getContent(), $b);
        $this->post("/runs/{$this->run->id}/arena", ['result_a' => $a[1], 'result_b' => $b[1], 'winner' => 'a'])
            ->assertRedirect("/runs/{$this->run->id}/arena");

        $vote = PairwiseVote::query()->firstOrFail();
        $this->assertSame(VoteWinner::A, $vote->winner);
        $winner = Result::query()->findOrFail($a[1])->model->ref();

        $this->get('/arena')->assertOk()->assertSee($winner)->assertSee('1016');
        $this->get("/runs/{$this->run->id}")->assertSee('1016');
    }

    public function test_arena_rejects_invalid_pairs(): void
    {
        $qwenResults = Result::query()->where('model_id', $this->qwen()->id)->pluck('id');

        $this->post("/runs/{$this->run->id}/arena", ['result_a' => $qwenResults[0], 'result_b' => $qwenResults[1], 'winner' => 'a'])
            ->assertSessionHasErrors('vote');
        $this->post("/runs/{$this->run->id}/arena", ['result_a' => 1, 'result_b' => 2, 'winner' => 'x'])
            ->assertSessionHasErrors('winner');
    }

    public function test_arena_without_pairs(): void
    {
        $this->post('/runs', ['scenario_id' => $this->digest()->id, 'input' => 'x', 'model_ids' => [$this->qwen()->id]]);
        $single = Run::query()->latest('id')->firstOrFail();

        $this->get("/runs/{$single->id}/arena")->assertOk()->assertSee('Немає пар');
    }

    public function test_judge_sees_the_rendered_user_message(): void
    {
        $this->artisan('bench:judge', ['run' => $this->run->id, '--judge' => 'groq:llama-x', '--sync' => true])->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_contains($r['messages'][0]['content'], 'strict evaluator')
            && str_contains($r['messages'][1]['content'], 'Title: Rate cut')
            && str_contains($r['messages'][1]['content'], 'editor of a news digest'));
    }

    public function test_judge_executor_is_idempotent(): void
    {
        $result = Result::query()->where('model_id', $this->qwen()->id)->firstOrFail();
        $executor = $this->app->make(JudgeExecutor::class);

        $executor->execute($result, $this->llama());
        $sent = count(Http::recorded());
        $executor->execute($result, $this->llama());

        $this->assertCount($sent, Http::recorded());
        $this->assertCount(1, $this->judgeEvaluations()->where('metric', 'judge_overall'));
    }

    public function test_quality_shows_scores_per_judge(): void
    {
        $this->artisan('bench:judge', ['run' => $this->run->id, '--judge' => 'groq:llama-x', '--sync' => true])->assertSuccessful();
        $this->artisan('bench:judge', ['run' => $this->run->id, '--judge' => 'groq:qwen/qwen3-32b', '--sync' => true])->assertSuccessful();

        $rows = collect($this->app->make(QualityReportBuilder::class)->build($this->run)->rows)->keyBy(fn ($r) => $r->model->model_id);

        $this->assertSame(['groq:llama-x' => 7.0], $rows['qwen/qwen3-32b']->judgeScores);
        $this->assertSame(['groq:qwen/qwen3-32b' => 7.0], $rows['llama-x']->judgeScores);
    }

    public function test_arena_pair_is_voted_once(): void
    {
        $page = $this->get("/runs/{$this->run->id}/arena")->getContent();
        preg_match('/name="result_a" value="(\d+)"/', $page, $a);
        preg_match('/name="result_b" value="(\d+)"/', $page, $b);

        $this->post("/runs/{$this->run->id}/arena", ['result_a' => $a[1], 'result_b' => $b[1], 'winner' => 'a'])->assertSessionHasNoErrors();
        $this->post("/runs/{$this->run->id}/arena", ['result_a' => $b[1], 'result_b' => $a[1], 'winner' => 'b'])->assertSessionHasErrors('vote');
        $this->assertSame(1, PairwiseVote::query()->count());
    }

    public function test_arena_says_when_all_pairs_are_voted(): void
    {
        $arena = $this->app->make(ArenaService::class);
        while (($pair = $arena->pick($this->run)) !== null) {
            $arena->vote($this->run, $pair[0]->id, $pair[1]->id, VoteWinner::Tie);
        }

        $this->assertSame(8, PairwiseVote::query()->count());   // 2 items x (2 qwen attempts x 2 llama attempts)
        $this->get("/runs/{$this->run->id}/arena")->assertOk()->assertSee('Усі пари');
    }

    public function test_judge_selection_must_be_unambiguous_and_usable(): void
    {
        $this->artisan('bench:judge', ['run' => $this->run->id, '--judge' => 'groq:*'])
            ->expectsOutputToContain('exactly one')
            ->assertFailed();

        $this->llama()->update(['available' => false]);
        $this->post("/runs/{$this->run->id}/judge", ['judge_model_id' => $this->llama()->id])->assertSessionHasErrors('judge_model_id');
    }
}
