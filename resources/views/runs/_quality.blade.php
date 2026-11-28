@php
    $num = fn (?float $v, int $p = 2) => $v === null ? '-' : (string) round($v, $p);
    $pct = fn (?float $v) => $v === null ? '-' : round($v * 100).'%';
    $showRepeats = $run->repeats > 1;
    $showMedian = count($matrix->models) > 1 && $matrix->hasScores();
@endphp
<section class="space-y-2">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Якість</h2>
        <div class="flex flex-wrap items-center gap-2">
            @if (count($matrix->models) > 1)
                <a class="btn" href="{{ route('runs.arena', $run) }}">Сліпе порівняння A/B @if ($quality->votes)<span class="muted">· {{ $quality->votes }}</span>@endif</a>
            @endif
            <form method="post" action="{{ route('runs.judge', $run) }}" class="flex items-center gap-2">
                @csrf
                <select name="judge_model_id" class="input" style="width: auto; max-width: 280px" required>
                    <option value="">Модель-суддя…</option>
                    @foreach ($judgeCandidates->groupBy(fn ($m) => $m->provider->code) as $code => $group)
                        <optgroup label="{{ $code }}">
                            @foreach ($group as $m)<option value="{{ $m->id }}">{{ $m->model_id }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
                <button class="btn">Оцінити суддею</button>
            </form>
        </div>
    </div>
    <div class="card overflow-x-auto">
        <table class="tbl">
            <thead>
            <tr>
                <th>Модель</th>
                @if ($quality->hasReference())
                    <th title="Статей з еталонною оцінкою">n</th>
                    <th title="Середня абсолютна похибка оцінки від еталону (менше - краще)">MAE</th>
                    <th title="Частка оцінок у межах ±1 від еталону">±1</th>
                    <th title="Кореляція рангів з еталоном: чи правильно модель впорядковує статті (1 - ідеально)">Spearman</th>
                    <th title="Середнє (оцінка - еталон): плюс - завищує, мінус - занижує">Зсув</th>
                    <th title="Частка збігу топ-{{ $quality->topK }} статей моделі з топом еталону">Топ-{{ $quality->topK }}</th>
                @endif
                @if ($showRepeats)<th title="Середнє стандартне відхилення оцінки між повторами (менше - стабільніше)">σ повторів</th>@endif
                @if ($showMedian)<th title="Середнє відхилення оцінки від медіани всіх моделей">Δ медіани</th>@endif
                @forelse ($quality->judges as $judge)
                    <th class="mono text-xs" title="Середня загальна оцінка 1-10 від судді {{ $judge }} (свої відповіді суддя не оцінює)">Суддя<br>{{ $judge }}</th>
                @empty
                    <th title="Запустіть суддю кнопкою вище">Суддя</th>
                @endforelse
                <th title="Ваша середня оцінка 1-5">Ваша</th>
                <th title="Рейтинг за сліпими порівняннями (старт 1000)">Elo</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($quality->rows as $q)
                <tr>
                    <td class="mono">{{ $q->model->ref() }}</td>
                    @if ($quality->hasReference())
                        <td>{{ $q->accuracy->count ?? 0 }}</td>
                        <td>{{ $num($q->accuracy?->mae) }}</td>
                        <td>{{ $pct($q->accuracy?->within1) }}</td>
                        <td>{{ $num($q->accuracy?->spearman) }}</td>
                        <td>{{ $q->accuracy ? sprintf('%+.2f', $q->accuracy->bias) : '-' }}</td>
                        <td>{{ $pct($q->accuracy?->topKOverlap) }}</td>
                    @endif
                    @if ($showRepeats)<td>{{ $num($q->repeatSpread) }}</td>@endif
                    @if ($showMedian)<td>{{ $num($q->medianDeviation) }}</td>@endif
                    @forelse ($quality->judges as $judge)
                        <td>{{ $num($q->judgeScores[$judge] ?? null, 1) }}</td>
                    @empty
                        <td>-</td>
                    @endforelse
                    <td>{{ $num($q->humanRating, 1) }}</td>
                    <td>{{ $q->elo ? round($q->elo->rating) : '-' }} @if ($q->elo)<span class="muted text-xs">{{ $q->elo->wins }}/{{ $q->elo->losses }}/{{ $q->elo->ties }}</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="text-xs muted space-y-1">
        @unless ($quality->hasReference())
            <p>Точність відносно еталону зʼявиться, коли статті датасету мають еталонні оцінки (<span class="mono">Розмітка еталону</span>).</p>
        @endunless
        @foreach ($quality->judgeAgreement as $judge => $agreement)
            <p>Суддя <span class="mono">{{ $judge }}</span>:
                @if ($agreement['n'] > 0)
                    узгодженість з вашими оцінками - Spearman {{ $num($agreement['rho']) }} на {{ $agreement['n'] }} відповідях.
                @else
                    оцініть кілька відповідей вручну, щоб перевірити, чи можна йому довіряти.
                @endif
            </p>
        @endforeach
        <p>Наведіть курсор на заголовок колонки, щоб побачити пояснення. Elo: перемоги/поразки/нічиї.</p>
    </div>
</section>
