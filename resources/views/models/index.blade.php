@extends('layouts.app')
@section('title', 'Моделі')
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Моделі</h1>
        <form method="post" action="{{ route('models.sync') }}" class="flex items-center gap-3">
            @csrf
            <label class="text-sm flex items-center gap-2"><input type="checkbox" name="discover" value="1"> шукати нові через API провайдерів</label>
            <button class="btn">Синхронізувати</button>
        </form>
    </div>
    <x-providers :providers="$providers" />
    <p class="text-sm muted">Нова модель або провайдер - запис у <span class="mono">config/bench.php</span> і «Синхронізувати». Знайдені через API моделі додаються вимкненими.</p>

    @foreach ($models as $code => $group)
        <div class="card overflow-x-auto" x-data="{ ping: {} }">
            <div class="px-4 pt-3 font-semibold">{{ $code }}</div>
            <table class="tbl">
                <thead><tr><th>Модель</th><th>Назва</th><th>Контекст</th><th>JSON</th><th>Джерело</th><th>Стан</th><th></th></tr></thead>
                <tbody>
                @foreach ($group as $model)
                    <tr @class(['opacity-50' => ! $model->available])>
                        <td class="mono">{{ $model->model_id }}</td>
                        <td>{{ $model->display_name }}</td>
                        <td>{{ $model->context_window ? number_format($model->context_window, 0, '.', ' ') : '' }}</td>
                        <td>{{ match ($model->supports_json) { true => 'так', false => 'ні', null => '?' } }}</td>
                        <td class="muted">{{ $model->source->value }}</td>
                        <td>
                            <form method="post" action="{{ route('models.toggle', $model) }}">
                                @csrf
                                <button class="btn text-xs">{{ $model->enabled ? '● увімкнена' : '○ вимкнена' }}</button>
                            </form>
                        </td>
                        <td class="whitespace-nowrap">
                            <button class="btn text-xs" type="button"
                                    @click="ping[{{ $model->id }}] = { label: '…' };
                                            fetch('{{ route('models.ping', $model) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } })
                                              .then(r => r.json()).then(d => ping[{{ $model->id }}] = d)
                                              .catch(() => ping[{{ $model->id }}] = { label: 'error', message: 'request failed' })">
                                ping
                            </button>
                            <span class="text-xs" x-show="ping[{{ $model->id }}]" x-cloak
                                  x-text="ping[{{ $model->id }}] ? ping[{{ $model->id }}].label + (ping[{{ $model->id }}].latency_ms ? ' · ' + ping[{{ $model->id }}].latency_ms + ' ms' : '') + (ping[{{ $model->id }}].message ? ' · ' + ping[{{ $model->id }}].message.slice(0, 80) : '') : ''"></span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endsection
