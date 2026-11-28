<?php

namespace App\Http\Controllers;

use App\Enums\SyncStatus;
use App\Llm\ProviderRegistry;
use App\Models\LlmModel;
use App\Services\Models\ModelManager;
use App\Services\Models\ModelSyncService;
use App\Services\Models\ProviderOverview;
use App\Services\Models\SyncReport;
use App\Services\Ping\ModelPinger;
use App\Services\Ping\PingTarget;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ModelController extends Controller
{
    public function index(ModelManager $manager, ProviderOverview $providers): View
    {
        return view('models.index', [
            'providers' => $providers->rows(),
            'models' => $manager->list()->groupBy(fn (LlmModel $m) => $m->provider->code),
        ]);
    }

    public function sync(Request $request, ModelSyncService $sync): RedirectResponse
    {
        $reports = $sync->sync(discover: $request->boolean('discover'));
        $summary = implode('; ', array_map(fn (SyncReport $r) => sprintf(
            '%s: %s +%d ~%d -%d',
            $r->provider, $r->status->value, $r->counts->added, $r->counts->updated, $r->counts->removed,
        ), $reports));
        $failed = array_filter($reports, fn (SyncReport $r) => $r->status === SyncStatus::Failed);

        return redirect()->route('models.index')->with($failed === [] ? 'status' : 'error', $summary);
    }

    public function toggle(LlmModel $model, ModelManager $manager): RedirectResponse
    {
        $manager->toggle($model);

        return redirect()->route('models.index');
    }

    public function ping(LlmModel $model, ModelPinger $pinger, ProviderRegistry $registry): JsonResponse
    {
        if (! $registry->has($model->provider->code)) {
            return response()->json(['status' => 'error', 'label' => 'error', 'message' => 'provider not in config'], 422);
        }

        $result = $pinger->ping(new PingTarget($registry->get($model->provider->code), $model->model_id));

        return response()->json([
            'status' => $result->status->value,
            'label' => $result->status->label(),
            'latency_ms' => $result->latencyMs,
            'message' => $result->reply ?? $result->error,
        ]);
    }
}
