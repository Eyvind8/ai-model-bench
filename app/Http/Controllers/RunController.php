<?php

namespace App\Http\Controllers;

use App\Evaluation\QualityReportBuilder;
use App\Http\Requests\RunFormRequest;
use App\Models\Dataset;
use App\Models\LlmModel;
use App\Models\Run;
use App\Models\Scenario;
use App\Runs\ForecastLine;
use App\Runs\Report\ResultsCsvExporter;
use App\Runs\Report\RunMatrixBuilder;
use App\Runs\Report\RunReportBuilder;
use App\Runs\RunCanceller;
use App\Runs\RunForecaster;
use App\Runs\RunLauncher;
use App\Runs\RunPlanner;
use App\Runs\RunProgress;
use App\Runs\RunRequest;
use App\Runs\RunRequestFactory;
use App\Services\Models\ModelManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunController extends Controller
{
    public function index(RunProgress $progress): View
    {
        $runs = Run::query()->with('dataset')->latest('id')->paginate(30);

        return view('runs.index', ['runs' => $runs, 'counts' => $runs->getCollection()->mapWithKeys(fn (Run $r) => [$r->id => $progress->counts($r)])]);
    }

    public function create(ModelManager $models): View
    {
        return view('runs.create', $this->formData($models));
    }

    public function playground(ModelManager $models): View
    {
        return view('runs.playground', $this->formData($models));
    }

    public function forecast(RunFormRequest $request, RunRequestFactory $factory, RunForecaster $forecaster): JsonResponse
    {
        try {
            $lines = $forecaster->forecast($this->runRequest($request, $factory));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['lines' => array_map(fn (ForecastLine $l) => [
            'model' => $l->model, 'requests' => $l->requests, 'remaining' => $l->remainingToday,
            'exhausted' => $l->exhausted, 'fits' => $l->fits(),
        ], $lines)]);
    }

    public function store(RunFormRequest $request, RunRequestFactory $factory, RunPlanner $planner, RunLauncher $launcher): RedirectResponse
    {
        try {
            $run = $planner->plan($this->runRequest($request, $factory));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['run' => $e->getMessage()])->withInput();
        }

        $queued = $launcher->dispatch($run);

        return redirect()->route('runs.show', $run)->with('status', "Запитів у черзі: {$queued}");
    }

    public function show(Run $run, RunReportBuilder $reports, RunMatrixBuilder $matrices, RunProgress $progress, QualityReportBuilder $quality, ModelManager $models): View
    {
        $run->load('dataset');
        $matrix = $matrices->build($run);

        return view('runs.show', [
            'run' => $run,
            'definition' => $run->definition(),
            'counts' => $progress->counts($run),
            'reports' => $reports->build($run),
            'matrix' => $matrix,
            'quality' => $quality->build($run, $matrix),
            'judgeCandidates' => $models->list()->filter(fn (LlmModel $m) => $m->available),
        ]);
    }

    public function progress(Run $run, RunProgress $progress): JsonResponse
    {
        $counts = $progress->counts($run);

        return response()->json([
            'status' => $run->status->value,
            'finished' => $counts['pending'] === 0,
            'counts' => $counts,
        ]);
    }

    public function cancel(Run $run, RunCanceller $canceller): RedirectResponse
    {
        $cancelled = $canceller->cancel($run);

        return redirect()->route('runs.show', $run)->with('status', "Скасовано запитів: {$cancelled}");
    }

    public function export(Run $run, ResultsCsvExporter $exporter): StreamedResponse
    {
        return response()->streamDownload(function () use ($run, $exporter) {
            $out = fopen('php://output', 'w');
            foreach ($exporter->rows($run) as $row) {
                fputcsv($out, $row, escape: '');
            }
            fclose($out);
        }, "run-{$run->id}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function runRequest(RunFormRequest $request, RunRequestFactory $factory): RunRequest
    {
        return $factory->make(
            scenarioSlug: (string) Scenario::query()->whereKey($request->integer('scenario_id'))->value('slug'),
            datasetId: $request->datasetId(),
            input: $request->adhocInput(),
            repeats: max(1, $request->integer('repeats', 1)),
            limit: $request->filled('limit') ? $request->integer('limit') : null,
            modelIds: $request->modelIds(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(ModelManager $models): array
    {
        return [
            'scenarios' => Scenario::query()->where('available', true)->orderBy('slug')->get(),
            'datasets' => Dataset::query()->orderByDesc('id')->get(),
            'models' => $models->list()->filter(fn (LlmModel $m) => $m->available)->groupBy(fn (LlmModel $m) => $m->provider->code),
        ];
    }
}
