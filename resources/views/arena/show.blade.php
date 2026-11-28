@extends('layouts.app')
@section('title', 'Сліпе порівняння · прогін #'.$run->id)
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a class="link text-sm" href="{{ route('runs.show', $run) }}">← Прогін #{{ $run->id }}</a>
            <h1 class="text-xl font-semibold mt-1">Сліпе порівняння</h1>
            <p class="text-sm muted">Назви моделей приховані, порядок A/B випадковий. Голосів у цьому прогоні: {{ $votes }}.</p>
        </div>
        <a class="link text-sm" href="{{ route('arena.index') }}">Загальний рейтинг →</a>
    </div>

    @if ($pair === null && $hasCandidates)
        <div class="card p-6 text-sm">✓ Усі пари цього прогону оцінено. <a class="link" href="{{ route('arena.index') }}">Рейтинг</a></div>
    @elseif ($pair === null)
        <div class="card p-6 text-sm muted">Немає пар для порівняння: потрібні успішні відповіді щонайменше двох моделей на ту саму статтю.</div>
    @else
        @php([$a, $b] = $pair)
        @php($source = $a->datasetItem?->input ?? (string) $run->adhoc_input)
        <details class="card p-4" open>
            <summary class="cursor-pointer font-medium">{{ $a->datasetItem?->title ?: 'Вхідний текст' }}</summary>
            <div class="text-sm muted mt-2 max-h-48 overflow-y-auto whitespace-pre-line">{{ \Illuminate\Support\Str::limit($source, 3000) }}</div>
        </details>

        <form method="post" action="{{ route('runs.arena.vote', $run) }}" class="space-y-4"
              x-data="{ sent: false }" @submit="if (sent) { $event.preventDefault(); } sent = true"
              @keydown.window="
                if (sent || $event.ctrlKey || $event.metaKey || $event.altKey || $event.repeat) return;
                if (['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) return;
                const map = { a: 'a', b: 'b', t: 'tie', x: 'both_bad' };
                const key = map[$event.key.toLowerCase()];
                if (key) { $event.preventDefault(); $refs[key].click(); }
              ">
            @csrf
            <input type="hidden" name="result_a" value="{{ $a->id }}">
            <input type="hidden" name="result_b" value="{{ $b->id }}">
            <div class="grid gap-4 md:grid-cols-2">
                @foreach (['A' => $a, 'B' => $b] as $label => $result)
                    <div class="card p-4 space-y-2">
                        <div class="font-semibold">Відповідь {{ $label }}</div>
                        <pre class="code">{{ $result->parsed_json ? json_encode($result->parsed_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $result->content }}</pre>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap justify-center gap-2">
                <button class="btn btn-primary" name="winner" value="a" x-ref="a">A краще <span class="mono opacity-75">A</span></button>
                <button class="btn" name="winner" value="tie" x-ref="tie">Нічия <span class="mono muted">T</span></button>
                <button class="btn" name="winner" value="both_bad" x-ref="both_bad">Обидві погані <span class="mono muted">X</span></button>
                <button class="btn btn-primary" name="winner" value="b" x-ref="b">B краще <span class="mono opacity-75">B</span></button>
            </div>
        </form>
    @endif
@endsection
