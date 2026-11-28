@extends('layouts.app')
@section('title', 'Відповідь #'.$result->id)
@section('content')
    @php
        $json = fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $human = $result->evaluations->firstWhere('type', \App\Enums\EvaluationType::Human);
        $auto = $result->evaluations->where('type', \App\Enums\EvaluationType::Auto);
    @endphp
    <div>
        <a class="link text-sm" href="{{ route('runs.show', $result->run) }}">← Прогін #{{ $result->run_id }}</a>
        <h1 class="text-xl font-semibold mt-1">Відповідь #{{ $result->id }} · <span class="mono">{{ $result->model->ref() }}</span></h1>
        <div class="flex flex-wrap items-center gap-3 mt-1 text-sm">
            <x-status :value="$result->status" />
            <span class="muted">спроба {{ $result->attempt_no }}</span>
            @if ($result->latency_ms)<span class="muted">{{ $result->latency_ms }} мс</span>@endif
            @if ($result->prompt_tokens !== null)<span class="muted">токени {{ $result->prompt_tokens }} → {{ $result->completion_tokens }}</span>@endif
            <span class="muted">JSON: {{ $result->json_mode?->value ?? '-' }}</span>
            @if ($result->retries)<span class="muted">повторів {{ $result->retries }}</span>@endif
            @if ($result->http_status)<span class="muted">HTTP {{ $result->http_status }}</span>@endif
        </div>
        @if ($result->error_message)<div class="text-sm mt-1" style="color: var(--critical)">{{ $result->error_message }}</div>@endif
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            @if ($result->parsed_json)
                <div class="card p-4"><div class="label">Розібраний JSON</div><pre class="code mono">{{ $json($result->parsed_json) }}</pre></div>
            @endif
            <div class="card p-4"><div class="label">Відповідь моделі</div><pre class="code">{{ $result->content ?? '—' }}</pre></div>
            <div class="card p-4"><div class="label">Запит</div><pre class="code mono">{{ $json($result->request_json) }}</pre></div>
            @if ($result->raw_response)
                <details class="card p-4"><summary class="label cursor-pointer">Сира відповідь API</summary><pre class="code mono mt-2">{{ $json($result->raw_response) }}</pre></details>
            @endif
        </div>
        <aside class="space-y-4">
            <form method="post" action="{{ route('results.rate', $result) }}" class="card p-4 space-y-3" x-data="{ rating: {{ (int) ($human?->value ?? 0) }} }">
                @csrf
                <div class="label">Ваша оцінка відповіді</div>
                <div class="flex gap-2">
                    @for ($r = 1; $r <= 5; $r++)
                        <button type="submit" name="rating" value="{{ $r }}" class="btn justify-center w-10"
                                :style="rating === {{ $r }} ? 'background: var(--series-1); color: #fff; border-color: var(--series-1)' : ''">{{ $r }}</button>
                    @endfor
                </div>
                <textarea name="comment" rows="3" class="input" maxlength="2000" placeholder="Коментар (необовʼязково)">{{ $human?->details }}</textarea>
                <p class="text-xs muted">1 - погано, 5 - відмінно. Повторна оцінка перезаписує попередню.</p>
            </form>
            @if ($auto->isNotEmpty())
                <div class="card p-4">
                    <div class="label">Автометрики</div>
                    <table class="tbl">
                        @foreach ($auto as $e)
                            <tr><td class="mono">{{ $e->metric }}</td><td>{{ round($e->value, 2) }}</td><td class="muted text-xs">{{ $e->details }}</td></tr>
                        @endforeach
                    </table>
                </div>
            @endif
            @if ($result->datasetItem)
                <div class="card p-4 space-y-1">
                    <div class="label">Стаття</div>
                    <div class="font-medium">{{ $result->datasetItem->title }}</div>
                    @if ($result->datasetItem->expected_score !== null)<div class="text-sm">Еталон: <b>{{ $result->datasetItem->expected_score }}</b></div>@endif
                    <div class="text-sm muted max-h-64 overflow-y-auto whitespace-pre-line">{{ \Illuminate\Support\Str::limit($result->datasetItem->input, 2000) }}</div>
                </div>
            @endif
        </aside>
    </div>
@endsection
