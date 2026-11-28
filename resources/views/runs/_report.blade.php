@php
    $pct = fn (?float $v) => $v === null ? '-' : round($v * 100).'%';
    $num = fn (?float $v, int $p = 0) => $v === null ? '-' : (string) round($v, $p);
    $labels = array_keys($definition->metrics);
@endphp
<section class="space-y-2">
    <h2 class="text-lg font-semibold">Зведення по моделях</h2>
    <div class="card overflow-x-auto">
        <table class="tbl">
            <thead>
            <tr>
                <th>Модель</th><th title="Успішні відповіді / всього">OK</th><th title="Помилки + ліміти серед завершених">Помилки</th>
                <th title="Відповідь містить JSON-обʼєкт">JSON</th><th title="JSON відповідає схемі сценарію">Схема</th>
                <th>p50, мс</th><th>p95, мс</th><th title="Середня кількість токенів відповіді">Токени</th>
                @foreach ($labels as $label)<th class="mono">{{ $label }}</th>@endforeach
            </tr>
            </thead>
            <tbody>
            @foreach ($reports as $r)
                <tr>
                    <td class="mono">{{ $r->model }}</td>
                    <td>{{ $r->statuses['ok'] ?? 0 }}/{{ $r->total }}</td>
                    <td>{{ $pct($r->errorRate()) }}</td>
                    <td>{{ $pct($r->jsonValidRate) }}</td>
                    <td>{{ $pct($r->schemaValidRate) }}</td>
                    <td>{{ $num($r->latencyP50) }}</td>
                    <td>{{ $num($r->latencyP95) }}</td>
                    <td>{{ $num($r->avgCompletionTokens) }}</td>
                    @foreach ($labels as $label)<td>{{ $num($r->metrics[$label] ?? null, 2) }}</td>@endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if ($labels)
        <p class="text-xs muted">Автометрики - середнє по успішних відповідях. <span class="mono">*_ok</span>, <span class="mono">*_match</span>: частка 0-1 (більше - краще); <span class="mono">score_abs_error</span>: середня похибка оцінки від еталону (менше - краще).</p>
    @endif
</section>
