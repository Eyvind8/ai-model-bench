@extends('layouts.app')
@section('title', 'Арена')
@section('content')
    <div>
        <h1 class="text-xl font-semibold">Арена: рейтинг Elo</h1>
        <p class="text-sm muted">За всіма сліпими порівняннями в усіх прогонах ({{ $votes }} голосів). Старт 1000, K = 32. «Обидві погані» рейтинг не змінює.</p>
    </div>
    <div class="card overflow-x-auto">
        <table class="tbl">
            <thead><tr><th>#</th><th>Модель</th><th>Elo</th><th>Порівнянь</th><th>Перемоги</th><th>Поразки</th><th>Нічиї</th><th>Обидві погані</th></tr></thead>
            <tbody>
            @forelse ($ratings as $model => $r)
                <tr>
                    <td class="muted">{{ $loop->iteration }}</td>
                    <td class="mono">{{ $model }}</td>
                    <td class="font-semibold">{{ round($r->rating) }}</td>
                    <td>{{ $r->games() }}</td>
                    <td>{{ $r->wins }}</td>
                    <td>{{ $r->losses }}</td>
                    <td>{{ $r->ties }}</td>
                    <td>{{ $r->bothBad }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Голосів ще немає. Відкрийте прогін з кількома моделями і натисніть «Сліпе порівняння A/B».</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
