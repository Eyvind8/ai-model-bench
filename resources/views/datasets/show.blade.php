@extends('layouts.app')
@section('title', $dataset->name)
@section('content')
    @php([$total, $ready, $pending, $failed, $labeled] = $stats)
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a class="link text-sm" href="{{ route('datasets.index') }}">← Датасети</a>
            <h1 class="text-xl font-semibold mt-1">{{ $dataset->name }} <span class="muted text-base">#{{ $dataset->id }}</span></h1>
            <div class="text-sm muted">Статей {{ $total }} · готові {{ $ready }} · завантажуються {{ $pending }} · помилки {{ $failed }} · розмічено {{ $labeled }}</div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn" href="{{ route('datasets.label', $dataset) }}">Розмітка еталону</a>
            <a class="btn btn-primary" href="{{ route('runs.create', ['dataset_id' => $dataset->id]) }}">Запустити прогін</a>
            @if ($failed > 0)
                <form method="post" action="{{ route('datasets.refetch', $dataset) }}">@csrf<button class="btn">Повторити невдалі</button></form>
            @endif
            <form method="post" action="{{ route('datasets.destroy', $dataset) }}" onsubmit="return confirm('Видалити датасет з усіма статтями?')">
                @csrf @method('delete')<button class="btn btn-danger">Видалити</button>
            </form>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3" x-data="{ tab: 'urls' }">
        <div class="card p-4 lg:col-span-3">
            <div class="flex gap-2 mb-3 text-sm">
                <button type="button" class="btn" :class="tab === 'urls' && 'btn-primary'" @click="tab = 'urls'">За URL</button>
                <button type="button" class="btn" :class="tab === 'file' && 'btn-primary'" @click="tab = 'file'">З файлу</button>
                <button type="button" class="btn" :class="tab === 'manual' && 'btn-primary'" @click="tab = 'manual'">Вручну</button>
            </div>
            <form x-show="tab === 'urls'" method="post" action="{{ route('datasets.urls', $dataset) }}" class="space-y-2">
                @csrf
                <textarea name="urls" rows="4" class="input mono" placeholder="https://site/article-1&#10;https://site/article-2">{{ old('urls') }}</textarea>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs muted">Один URL на рядок. Сторінки завантажуються у фоні (воркер), текст чиститься Readability.</span>
                    <button class="btn btn-primary">Завантажити</button>
                </div>
            </form>
            <form x-show="tab === 'file'" x-cloak method="post" action="{{ route('datasets.import', $dataset) }}" enctype="multipart/form-data" class="space-y-2">
                @csrf
                <input type="file" name="file" accept=".csv,.json,.jsonl" class="input">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs muted">CSV/JSON/JSONL. Колонки: <span class="mono">title, text|input, url, expected_score, expected_announcement</span>. Рядки лише з url завантажуються.</span>
                    <button class="btn btn-primary">Імпортувати</button>
                </div>
            </form>
            <form x-show="tab === 'manual'" x-cloak method="post" action="{{ route('datasets.items.store', $dataset) }}" class="grid gap-2 sm:grid-cols-4">
                @csrf
                <input name="title" class="input sm:col-span-3" placeholder="Заголовок" value="{{ old('title') }}">
                <input name="expected_score" type="number" min="1" max="10" class="input" placeholder="Еталон 1-10" value="{{ old('expected_score') }}">
                <textarea name="input" rows="5" class="input sm:col-span-4" placeholder="Текст статті" required>{{ old('input') }}</textarea>
                <div class="sm:col-span-4 text-right"><button class="btn btn-primary">Додати</button></div>
            </form>
        </div>
    </div>

    <div class="card overflow-x-auto" x-data x-init="@if ($pending > 0) setTimeout(() => location.reload(), 4000) @endif">
        <table class="tbl">
            <thead><tr><th>#</th><th>Заголовок</th><th>Символів</th><th>Мова</th><th>Еталон</th><th>Статус</th><th>Помилка / джерело</th><th></th></tr></thead>
            <tbody>
            @forelse ($items as $item)
                <tr>
                    <td class="muted">{{ $item->id }}</td>
                    <td class="max-w-[320px]">{{ \Illuminate\Support\Str::limit($item->title ?: mb_substr($item->input, 0, 80), 90) }}</td>
                    <td>{{ number_format(mb_strlen($item->input), 0, '.', ' ') }}</td>
                    <td class="mono">{{ $item->meta_json['lang'] ?? '' }}</td>
                    <td>{{ $item->expected_score ?? '-' }}</td>
                    <td><x-status :value="$item->fetch_status ?? 'ok'" /></td>
                    <td class="text-xs max-w-[360px] break-all">
                        @if ($item->fetch_error)<span style="color: var(--critical)">{{ $item->fetch_error }}</span>
                        @elseif ($item->source_url)<a class="link" href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit($item->source_url, 70) }}</a>@endif
                    </td>
                    <td class="whitespace-nowrap text-sm">
                        <a class="link" href="{{ route('datasets.items.edit', [$dataset, $item]) }}">{{ $item->fetch_status?->value === 'failed' ? 'вставити текст' : 'редагувати' }}</a>
                        <form method="post" action="{{ route('datasets.items.destroy', [$dataset, $item]) }}" class="inline" onsubmit="return confirm('Видалити статтю?')">
                            @csrf @method('delete')<button class="ml-2 muted hover:underline">видалити</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Статей ще немає - додайте їх вище.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
