@extends('layouts.app')
@section('title', 'Огляд')
@section('content')
    <h1 class="text-xl font-semibold">Провайдери</h1>
    <x-providers :providers="$providers" />
    <p class="text-sm muted">Ключі задаються в <span class="mono">.env</span>, потім <span class="mono">make restart</span>. Моделі та сценарії - у <span class="mono">config/bench.php</span> і <span class="mono">config/scenarios.php</span>.</p>

    <div class="flex items-center justify-between pt-2">
        <h2 class="text-lg font-semibold">Останні прогони</h2>
        <a class="link text-sm" href="{{ route('runs.index') }}">Усі прогони →</a>
    </div>
    <x-runs-table :runs="$runs" :counts="$counts" />

    <div class="flex items-center justify-between pt-2">
        <h2 class="text-lg font-semibold">Датасети</h2>
        <a class="link text-sm" href="{{ route('datasets.index') }}">Усі датасети →</a>
    </div>
    <div class="card overflow-x-auto">
        <table class="tbl">
            <thead><tr><th>Назва</th><th>Статей</th><th>Готові</th><th>Завантажуються</th><th>Помилки</th><th>Розмічено</th></tr></thead>
            <tbody>
            @forelse ($datasets as $row)
                @php([$total, $ready, $pending, $failed, $labeled] = $row['stats'])
                <tr>
                    <td><a class="link" href="{{ route('datasets.show', $row['dataset']) }}">{{ $row['dataset']->name }}</a></td>
                    <td>{{ $total }}</td><td>{{ $ready }}</td><td>{{ $pending }}</td><td>{{ $failed }}</td><td>{{ $labeled }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Датасетів ще немає. <a class="link" href="{{ route('datasets.index') }}">Створити</a></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
