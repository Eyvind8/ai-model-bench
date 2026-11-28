@extends('layouts.app')
@section('title', 'Сценарії')
@section('content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Сценарії</h1>
        <form method="post" action="{{ route('scenarios.sync') }}">@csrf<button class="btn">Синхронізувати з конфігом</button></form>
    </div>
    <p class="text-sm muted">Сценарії описуються в <span class="mono">config/scenarios.php</span>: промпт, формат відповіді, автометрики.</p>
    <div class="card overflow-x-auto">
        <table class="tbl">
            <thead><tr><th>Slug</th><th>Назва</th><th>Формат</th><th>Метрики</th><th>Доступний</th></tr></thead>
            <tbody>
            @foreach ($scenarios as $scenario)
                <tr>
                    <td><a class="link mono" href="{{ route('scenarios.show', $scenario) }}">{{ $scenario->slug }}</a></td>
                    <td>{{ $scenario->name }}</td>
                    <td class="mono">{{ $scenario->response_format->value }}</td>
                    <td class="mono muted">{{ implode(', ', array_keys($scenario->metrics_json ?? [])) }}</td>
                    <td><x-status :value="$scenario->available ? 'yes' : 'no'" /></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
