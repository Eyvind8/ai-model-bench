<?php

namespace App\Runs;

use App\Enums\ResultStatus;
use App\Enums\RunStatus;
use App\Models\LlmModel;
use App\Models\Run;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates a run with all its pending results (input x model x attempt) and stored requests.
 * Results are interleaved by model so a queue worker spreads load across providers.
 */
final class RunPlanner
{
    public function __construct(
        private readonly RunInputResolver $inputs,
        private readonly RequestBuilder $requests,
    ) {}

    public function plan(RunRequest $request): Run
    {
        $inputs = $this->inputs->resolve($request);
        $this->assertPlannable($request, $inputs);

        return DB::transaction(function () use ($request, $inputs) {
            $run = Run::query()->create([
                'scenario_id' => $request->scenario->id,
                'dataset_id' => $request->dataset?->id,
                'adhoc_input' => $request->dataset === null ? $request->adhocInput : null,
                'repeats' => $request->repeats,
                'status' => RunStatus::Pending,
                'scenario_snapshot' => $request->scenario->toDefinition()->toArray(),
            ]);
            $run->models()->attach($request->models->pluck('id')->all());
            $this->createResults($run, $request, $inputs);

            return $run;
        });
    }

    /**
     * @param  list<RunInput>  $inputs
     */
    private function createResults(Run $run, RunRequest $request, array $inputs): void
    {
        $scenario = $run->definition();

        for ($attempt = 1; $attempt <= $request->repeats; $attempt++) {
            foreach ($inputs as $input) {
                $request->models->each(function (LlmModel $model) use ($run, $scenario, $input, $attempt) {
                    [$chat, $mode] = $this->requests->build($scenario, $model, $input);
                    $run->results()->create([
                        'model_id' => $model->id,
                        'dataset_item_id' => $input->item?->id,
                        'attempt_no' => $attempt,
                        'status' => ResultStatus::Pending,
                        'request_json' => $chat->toPayload(),
                        'json_mode' => $mode,
                    ]);
                });
            }
        }
    }

    /**
     * @param  list<RunInput>  $inputs
     */
    private function assertPlannable(RunRequest $request, array $inputs): void
    {
        match (true) {
            $request->models->isEmpty() => throw new InvalidArgumentException('No models selected'),
            $inputs === [] => throw new InvalidArgumentException('Nothing to send: dataset has no ready items or input is empty'),
            $request->repeats < 1 => throw new InvalidArgumentException('repeats must be >= 1'),
            default => null,
        };
    }
}
