@extends('layouts.app')
@section('title', 'Датасети')
@section('content')
    <h1 class="text-xl font-semibold">Датасети</h1>
    <form method="post" action="{{ route('datasets.store') }}" class="card p-4 flex flex-wrap items-end gap-3">
        @csrf
        <div class="flex-1 min-w-[220px]"><label class="label" for="name">Новий датасет</label><input id="name" name="name" class="input" placeholder="Напр. Новини економіки" value="{{ old('name') }}" required></div>
        <button class="btn btn-primary">Створити</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="tbl">
            <thead><tr><th>#</th><th>Назва</th><th>Статей</th><th>Готові</th><th>Завантажуються</th><th>Помилки</th><th>Розмічено</th><th></th></tr></thead>
            <tbody>
            @forelse ($datasets as $row)
                @php([$total, $ready, $pending, $failed, $labeled] = $row['stats'])
                <tr>
                    <td>{{ $row['dataset']->id }}</td>
                    <td><a class="link" href="{{ route('datasets.show', $row['dataset']) }}">{{ $row['dataset']->name }}</a></td>
                    <td>{{ $total }}</td><td>{{ $ready }}</td><td>{{ $pending }}</td><td>{{ $failed }}</td><td>{{ $labeled }} / {{ $ready }}</td>
                    <td><a class="link text-sm" href="{{ route('datasets.label', $row['dataset']) }}">розмітка</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Датасетів ще немає.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
