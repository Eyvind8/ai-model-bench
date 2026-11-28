{{-- Single-series horizontal bars: median latency per model (one axis, thin bars, direct value labels). --}}
@php
    $bars = collect($reports)->filter(fn ($r) => $r->latencyP50 !== null)->sortBy('latencyP50')->values();
    $max = max(1, (float) $bars->max('latencyP95'));
@endphp
@if ($bars->isNotEmpty())
    <section class="space-y-2">
        <h2 class="text-lg font-semibold">Затримка відповіді</h2>
        <div class="card p-4 space-y-2">
            @foreach ($bars as $r)
                <div class="grid items-center gap-3" style="grid-template-columns: minmax(140px, 260px) 1fr 110px"
                     title="{{ $r->model }}: p50 {{ round($r->latencyP50) }} мс, p95 {{ round($r->latencyP95) }} мс">
                    <div class="mono text-xs truncate">{{ $r->model }}</div>
                    <div class="relative h-4">
                        <div class="absolute inset-y-0 left-0 rounded-r" style="background: var(--series-1); width: {{ $r->latencyP50 / $max * 100 }}%; min-width: 2px; height: 10px; top: 3px"></div>
                        <div class="absolute" style="left: {{ $r->latencyP95 / $max * 100 }}%; top: 0; bottom: 0; width: 2px; background: var(--text-2)"></div>
                    </div>
                    <div class="text-xs text-right">{{ round($r->latencyP50) }} <span class="muted">/ {{ round($r->latencyP95) }} мс</span></div>
                </div>
            @endforeach
            <p class="text-xs muted pt-1">Смуга - медіана (p50), риска - p95.</p>
        </div>
    </section>
@endif
