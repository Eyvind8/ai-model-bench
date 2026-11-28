@props(['runs', 'counts'])
<div class="card overflow-x-auto">
    <table class="tbl">
        <thead><tr><th>#</th><th>Сценарій</th><th>Вхід</th><th>Статус</th><th>OK</th><th>В черзі</th><th>Помилки</th><th>Створено</th></tr></thead>
        <tbody>
        @forelse ($runs as $run)
            @php($c = $counts[$run->id])
            <tr>
                <td><a class="link" href="{{ route('runs.show', $run) }}">#{{ $run->id }}</a></td>
                <td class="mono">{{ $run->definition()->slug }}</td>
                <td>{{ $run->dataset ? $run->dataset->name : 'ad-hoc' }}</td>
                <td><x-status :value="$run->status" /></td>
                <td>{{ $c['ok'] }}</td>
                <td>{{ $c['pending'] }}</td>
                <td>{{ $c['error'] + $c['rate_limited'] }}</td>
                <td class="muted whitespace-nowrap">{{ $run->created_at->format('Y-m-d H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">Прогонів ще немає. <a class="link" href="{{ route('runs.create') }}">Створити</a></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
