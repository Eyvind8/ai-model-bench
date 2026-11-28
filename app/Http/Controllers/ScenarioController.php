<?php

namespace App\Http\Controllers;

use App\Models\Scenario;
use App\Scenarios\ScenarioSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ScenarioController extends Controller
{
    public function index(): View
    {
        return view('scenarios.index', ['scenarios' => Scenario::query()->orderByDesc('available')->orderBy('slug')->get()]);
    }

    public function show(Scenario $scenario): View
    {
        return view('scenarios.show', ['scenario' => $scenario]);
    }

    public function sync(ScenarioSyncService $sync): RedirectResponse
    {
        $counts = $sync->sync();

        return redirect()->route('scenarios.index')->with('status', "Сценарії: +{$counts->added} ~{$counts->updated} -{$counts->removed}");
    }
}
