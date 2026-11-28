@extends('layouts.app')
@section('title', 'Розмітка · '.$dataset->name)
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a class="link text-sm" href="{{ route('datasets.show', $dataset) }}">← {{ $dataset->name }}</a>
            <h1 class="text-xl font-semibold mt-1">Розмітка еталону</h1>
        </div>
        <div class="text-sm">
            Стаття {{ $position }} з {{ $total }} · розмічено <b>{{ $labeled }} / {{ $total }}</b>
            <div class="h-1.5 mt-1 rounded" style="background: var(--border); width: 220px">
                <div class="h-1.5 rounded" style="background: var(--series-1); width: {{ $total ? round($labeled / $total * 100) : 0 }}%"></div>
            </div>
        </div>
    </div>

    <form method="post" action="{{ route('datasets.items.label', [$dataset, $item]) }}" id="label-form"
          x-data="{ score: {{ (int) old('expected_score', $item->expected_score ?? 0) }} }"
          @keydown.window="
            if ($event.target.tagName === 'INPUT' || $event.target.tagName === 'TEXTAREA') return;
            if (/^[0-9]$/.test($event.key)) { $refs['s' + ($event.key === '0' ? 10 : $event.key)].click(); }
            @if ($previous) if ($event.key === 'ArrowLeft') location = '{{ route('datasets.label', [$dataset, 'item' => $previous->id]) }}'; @endif
            @if ($next) if ($event.key === 'ArrowRight') location = '{{ route('datasets.label', [$dataset, 'item' => $next->id]) }}'; @endif
          "
          class="grid gap-4 lg:grid-cols-3">
        @csrf
        <article class="card p-5 lg:col-span-2">
            <h2 class="text-lg font-semibold">{{ $item->title ?: 'Без заголовка' }}</h2>
            @if ($item->source_url)<a class="link text-xs break-all" href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer">{{ $item->source_url }}</a>@endif
            <div class="mt-3 text-[15px] leading-relaxed whitespace-pre-line max-h-[70vh] overflow-y-auto">{{ $item->input }}</div>
        </article>
        <aside class="card p-5 space-y-4 self-start">
            <div>
                <div class="label">Важливість для дайджесту</div>
                <div class="grid grid-cols-5 gap-2">
                    @for ($s = 1; $s <= 10; $s++)
                        <button type="submit" name="expected_score" value="{{ $s }}" x-ref="s{{ $s }}" class="btn justify-center"
                                :style="score === {{ $s }} ? 'background: var(--series-1); color: #fff; border-color: var(--series-1)' : ''">{{ $s }}</button>
                    @endfor
                </div>
                <p class="text-xs muted mt-2">Клавіші <span class="mono">1-9</span>, <span class="mono">0</span> = 10 - зберегти і далі. <span class="mono">←</span> <span class="mono">→</span> - навігація.</p>
            </div>
            <div>
                <label class="label" for="ann">Еталонний анонс (необовʼязково)</label>
                <textarea id="ann" name="expected_announcement" rows="4" maxlength="1000" class="input">{{ old('expected_announcement', $item->expected_announcement) }}</textarea>
            </div>
            <div class="flex justify-between text-sm">
                @if ($previous)<a class="link" href="{{ route('datasets.label', [$dataset, 'item' => $previous->id]) }}">← попередня</a>@else<span></span>@endif
                @if ($next)<a class="link" href="{{ route('datasets.label', [$dataset, 'item' => $next->id]) }}">наступна →</a>@endif
            </div>
        </aside>
    </form>
@endsection
