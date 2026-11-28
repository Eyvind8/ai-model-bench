<?php

namespace App\Http\Controllers;

use App\Evaluation\Judge\JudgeService;
use App\Llm\ProviderRegistry;
use App\Models\LlmModel;
use App\Models\Run;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class JudgeController extends Controller
{
    public function store(Request $request, Run $run, JudgeService $judges, ProviderRegistry $registry): RedirectResponse
    {
        $data = $request->validate(['judge_model_id' => ['required', 'integer', 'exists:models,id']]);
        $judge = LlmModel::query()->with('provider')->findOrFail($data['judge_model_id']);

        $code = $judge->provider->code;
        if (! $judge->available || ! $registry->has($code) || ! $registry->get($code)->isConfigured()) {
            return back()->withErrors(['judge_model_id' => "{$judge->ref()} is not usable: model unavailable or provider without API key"]);
        }

        $queued = $judges->dispatch($run, $judge);

        return redirect()->route('runs.show', $run)->with('status', "Суддя {$judge->ref()}: у черзі {$queued} відповідей (власні відповіді судді пропускаються)");
    }
}
