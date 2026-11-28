{{-- Article x model score heatmap: one sequential hue, value printed in every cell (small table), reference column first. --}}
@php
    $step = fn (?float $s) => $s === null ? null : max(1, min(7, (int) ceil($s / 10 * 7)));
    $cell = function (?float $s) use ($step) {
        $n = $step($s);
        return $n === null ? '' : "background: var(--seq-{$n}00); color: var(--seq-ink-{$n})";
    };
@endphp
@push('head')
<style>
    :root { --seq-ink-1: #191919; --seq-ink-2: #191919; --seq-ink-3: #191919; --seq-ink-4: #fff; --seq-ink-5: #fff; --seq-ink-6: #fff; --seq-ink-7: #fff; }
    @media (prefers-color-scheme: dark) {
        :root:where(:not([data-theme="light"])) { --seq-ink-1: #fff; --seq-ink-2: #fff; --seq-ink-3: #fff; --seq-ink-4: #fff; --seq-ink-5: #fff; --seq-ink-6: #191919; --seq-ink-7: #191919; }
    }
    .heat td.v { text-align: center; font-weight: 600; font-variant-numeric: tabular-nums; min-width: 56px; border: 2px solid var(--surface); }
    .heat-key span { display: inline-block; width: 26px; height: 14px; border-radius: 3px; }
</style>
@endpush
<section class="space-y-2">
    <h2 class="text-lg font-semibold">Оцінки: стаття × модель</h2>
    <div class="card overflow-x-auto">
        <table class="tbl heat">
            <thead>
            <tr>
                <th>Стаття</th><th class="text-center">Еталон</th>
                @foreach ($matrix->models as $model)<th class="mono text-xs text-center" style="white-space: normal; max-width: 140px">{{ $model->ref() }}</th>@endforeach
            </tr>
            </thead>
            <tbody>
            @foreach ($matrix->rows as $row)
                <tr>
                    <td class="max-w-[280px] truncate" title="{{ $row->label }}">{{ $row->label }}</td>
                    <td class="v" style="{{ $cell($row->expectedScore) }}">{{ $row->expectedScore ?? '-' }}</td>
                    @foreach ($matrix->models as $model)
                        @php($score = $row->meanScore($model->id, $matrix->scoreField))
                        <td class="v" style="{{ $cell($score) }}" title="{{ $model->ref() }} · {{ $row->label }}: {{ $score === null ? 'немає оцінки' : round($score, 1) }}">
                            {{ $score === null ? '-' : round($score, 1) }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="flex flex-wrap items-center gap-2 text-xs muted heat-key">
        <span style="width:auto;height:auto">1</span>
        @for ($n = 1; $n <= 7; $n++)<span style="background: var(--seq-{{ $n }}00)" title="крок {{ $n }}"></span>@endfor
        <span style="width:auto;height:auto">10</span>
        <span style="width:auto;height:auto" class="ml-2">Оцінка 1-10. При кількох повторах - середнє.</span>
    </div>
</section>
