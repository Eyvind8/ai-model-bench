@extends('layouts.app')
@section('title', $scenario->slug)
@section('content')
    <div>
        <a class="link text-sm" href="{{ route('scenarios.index') }}">← Сценарії</a>
        <h1 class="text-xl font-semibold mt-1">{{ $scenario->name }} <span class="mono muted">{{ $scenario->slug }}</span></h1>
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card p-4 space-y-3">
            <div><div class="label">System prompt</div><pre class="code mono">{{ $scenario->system_prompt }}</pre></div>
            <div><div class="label">User template</div><pre class="code mono">{{ $scenario->user_template }}</pre></div>
            <div class="text-sm">Параметри: <span class="mono">{{ json_encode($scenario->params_json, JSON_UNESCAPED_UNICODE) }}</span></div>
            @if ($scenario->judge_rubric)<div class="text-sm">Критерії судді: {{ $scenario->judge_rubric }}</div>@endif
        </div>
        <div class="card p-4 space-y-3">
            <div class="text-sm">Формат відповіді: <span class="mono">{{ $scenario->response_format->value }}</span></div>
            @if ($scenario->json_schema)
                <div><div class="label">JSON Schema</div><pre class="code mono">{{ json_encode($scenario->json_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>
            @endif
            <div>
                <div class="label">Автометрики</div>
                <table class="tbl">
                    @foreach ($scenario->metrics_json ?? [] as $label => $options)
                        <tr><td class="mono">{{ $label }}</td><td class="mono muted">{{ json_encode($options, JSON_UNESCAPED_UNICODE) }}</td></tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>
@endsection
