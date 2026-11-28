@extends('layouts.app')
@section('title', 'Стаття #'.$item->id)
@section('content')
    <div>
        <a class="link text-sm" href="{{ route('datasets.show', $dataset) }}">← {{ $dataset->name }}</a>
        <h1 class="text-xl font-semibold mt-1">Стаття #{{ $item->id }}</h1>
        @if ($item->source_url)
            <a class="link text-sm break-all" href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer">{{ $item->source_url }}</a>
        @endif
        @if ($item->fetch_error)
            <div class="text-sm mt-1" style="color: var(--critical)">{{ $item->fetch_error }}</div>
        @endif
    </div>
    <form method="post" action="{{ route('datasets.items.update', [$dataset, $item]) }}" class="card p-4 grid gap-3 sm:grid-cols-4">
        @csrf @method('put')
        <div class="sm:col-span-3"><label class="label" for="title">Заголовок</label><input id="title" name="title" class="input" value="{{ old('title', $item->title) }}"></div>
        <div><label class="label" for="score">Еталонна оцінка 1-10</label><input id="score" name="expected_score" type="number" min="1" max="10" class="input" value="{{ old('expected_score', $item->expected_score) }}"></div>
        <div class="sm:col-span-4"><label class="label" for="input">Текст</label><textarea id="input" name="input" rows="18" class="input" required>{{ old('input', $item->input) }}</textarea></div>
        <div class="sm:col-span-4"><label class="label" for="ann">Еталонний анонс</label><input id="ann" name="expected_announcement" class="input" maxlength="1000" value="{{ old('expected_announcement', $item->expected_announcement) }}"></div>
        <div class="sm:col-span-4 flex justify-end gap-2">
            <a class="btn" href="{{ route('datasets.show', $dataset) }}">Скасувати</a>
            <button class="btn btn-primary">Зберегти</button>
        </div>
    </form>
@endsection
