@extends('layouts.app')
@section('title', 'Прогін #'.$run->id)
@section('content')
    @php
        $total = array_sum($counts);
        $finishedCount = $total - $counts['pending'];
        $active = $counts['pending'] > 0 && $run->status->value !== 'cancelled';
    @endphp
    <div class="flex flex-wrap items-start justify-between gap-3"
         x-data="runProgress({{ $active ? 'true' : 'false' }}, {{ $finishedCount }})" x-init="start()">
        <div>
            <a class="link text-sm" href="{{ route('runs.index') }}">← Прогони</a>
            <h1 class="text-xl font-semibold mt-1">Прогін #{{ $run->id }} <span class="mono muted text-base">{{ $definition->slug }}</span></h1>
            <div class="text-sm muted">
                {{ $run->dataset ? 'Датасет: '.$run->dataset->name : 'Ad-hoc' }} · повторів {{ $run->repeats }} · {{ $run->created_at->format('Y-m-d H:i') }}
            </div>
            <div class="flex items-center gap-3 mt-2 text-sm">
                <x-status :value="$run->status" />
                <span>{{ $finishedCount }} / {{ $total }}</span>
                @foreach (['ok' => 'ok', 'error' => 'помилки', 'rate_limited' => 'ліміт'] as $key => $label)
                    @if ($counts[$key])<span class="muted">{{ $label }}: {{ $counts[$key] }}</span>@endif
                @endforeach
            </div>
            <div class="h-1.5 mt-2 rounded" style="background: var(--border); width: 280px">
                <div class="h-1.5 rounded" style="background: var(--series-1); width: {{ $total ? round($finishedCount / $total * 100) : 0 }}%"></div>
            </div>
            <p class="text-xs mt-2" x-show="stalled" x-cloak style="color: var(--serious)">
                ! Прогрес не змінюється. Перевірте воркер: <span class="mono">make up</span> / <span class="mono">docker compose logs worker</span>. Можливо, модель чекає на ліміт.
            </p>
        </div>
        <div class="flex gap-2">
            <a class="btn" href="{{ route('runs.export', $run) }}">CSV</a>
            @if ($active)
                <form method="post" action="{{ route('runs.cancel', $run) }}">@csrf<button class="btn btn-danger">Скасувати</button></form>
            @endif
        </div>
    </div>

    @include('runs._report')
    @include('runs._latency')
    @if ($matrix->hasScores() && $run->dataset)
        @include('runs._heatmap')
    @endif
    @include('runs._answers')
@endsection

@push('scripts')
<script>
    function runProgress(active, finished) {
        return {
            stalled: false, unchanged: 0,
            start() {
                if (!active) return;
                setInterval(() => fetch('{{ route('runs.progress', $run) }}', { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(p => {
                        const done = Object.values(p.counts).reduce((a, b) => a + b, 0) - p.counts.pending;
                        if (p.finished || done !== finished) { location.reload(); return; }
                        this.stalled = ++this.unchanged > 20;
                    }), 3000);
            },
        };
    }
</script>
@endpush
