<?php

namespace App\Http\Controllers;

use App\Datasets\DatasetStats;
use App\Models\Dataset;
use App\Models\Run;
use App\Runs\RunProgress;
use App\Services\Models\ProviderOverview;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(ProviderOverview $providers, RunProgress $progress, DatasetStats $stats): View
    {
        $runs = Run::query()->with('dataset')->latest('id')->limit(8)->get();

        return view('dashboard', [
            'providers' => $providers->rows(),
            'runs' => $runs,
            'counts' => $runs->mapWithKeys(fn (Run $run) => [$run->id => $progress->counts($run)]),
            'datasets' => Dataset::query()->latest('id')->limit(8)->get()->map(fn (Dataset $d) => ['dataset' => $d, 'stats' => $stats->forDataset($d)]),
        ]);
    }
}
