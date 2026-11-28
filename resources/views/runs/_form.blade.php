{{-- Shared run form. $adhoc = playground mode (text input instead of dataset). --}}
<form method="post" action="{{ route('runs.store') }}" class="grid gap-4 lg:grid-cols-3"
      x-data="runForm()" @change="forecast()" @input.debounce.600ms="forecast()" x-init="forecast()">
    @csrf
    <div class="card p-4 space-y-4 lg:col-span-2">
        <div>
            <label class="label" for="scenario">Сценарій</label>
            <select id="scenario" name="scenario_id" class="input" required>
                @foreach ($scenarios as $scenario)
                    <option value="{{ $scenario->id }}" @selected(old('scenario_id') == $scenario->id)>{{ $scenario->slug }} - {{ $scenario->name }}</option>
                @endforeach
            </select>
        </div>

        @if ($adhoc)
            <div>
                <label class="label" for="input">Текст</label>
                <textarea id="input" name="input" rows="12" class="input" placeholder="Вставте текст статті або будь-який вхід для сценарію" required>{{ old('input') }}</textarea>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="sm:col-span-3">
                    <label class="label" for="dataset">Датасет</label>
                    <select id="dataset" name="dataset_id" class="input" required>
                        @foreach ($datasets as $dataset)
                            <option value="{{ $dataset->id }}" @selected(old('dataset_id', request('dataset_id')) == $dataset->id)>#{{ $dataset->id }} {{ $dataset->name }}</option>
                        @endforeach
                    </select>
                    @if ($datasets->isEmpty())<p class="text-xs mt-1" style="color: var(--critical)">Спершу <a class="link" href="{{ route('datasets.index') }}">створіть датасет</a> або скористайтесь <a class="link" href="{{ route('playground') }}">Playground</a>.</p>@endif
                </div>
                <div>
                    <label class="label" for="repeats">Повторів</label>
                    <input id="repeats" name="repeats" type="number" min="1" max="10" value="{{ old('repeats', 1) }}" class="input">
                </div>
                <div>
                    <label class="label" for="limit">Перші N статей</label>
                    <input id="limit" name="limit" type="number" min="1" value="{{ old('limit') }}" class="input" placeholder="усі">
                </div>
            </div>
        @endif

        <div>
            <div class="label">Моделі</div>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($models as $code => $group)
                    <fieldset class="rounded-lg p-3" style="border: 1px solid var(--border)">
                        <legend class="px-1 text-sm font-semibold">{{ $code }}</legend>
                        @foreach ($group as $model)
                            <label class="flex items-center gap-2 text-sm py-0.5">
                                <input type="checkbox" name="model_ids[]" value="{{ $model->id }}"
                                       @checked(in_array($model->id, old('model_ids', [])) || (! old('model_ids') && $model->enabled))>
                                <span class="mono">{{ $model->model_id }}</span>
                            </label>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>
        </div>
    </div>

    <aside class="card p-4 space-y-3 self-start">
        <div class="font-semibold">Прогноз запитів</div>
        <p class="text-xs muted" x-show="error" x-text="error"></p>
        <table class="tbl" x-show="lines.length" x-cloak>
            <thead><tr><th>Модель</th><th>Запитів</th><th>Лишилось</th></tr></thead>
            <tbody>
            <template x-for="line in lines" :key="line.model">
                <tr>
                    <td class="mono text-xs break-all" x-text="line.model"></td>
                    <td x-text="line.requests"></td>
                    <td :style="line.fits ? '' : 'color: var(--critical)'" x-text="(line.fits ? '' : '⚠ ') + (line.exhausted ? 'вичерпано' : (line.remaining ?? '∞'))"></td>
                </tr>
            </template>
            </tbody>
        </table>
        <p class="text-xs muted">Ліміти free-тарифів з <span class="mono">config/bench.php</span>, лічильники локальні. Запити понад денний ліміт завершаться як <span class="mono">rate_limited</span>.</p>
        <button class="btn btn-primary w-full justify-center">Запустити</button>
        <p class="text-xs muted">Виконує воркер черги (<span class="mono">make up</span>).</p>
    </aside>
</form>

@push('scripts')
<script>
    function runForm() {
        return {
            lines: [], error: '',
            forecast() {
                const data = new FormData(this.$root);
                fetch('{{ route('runs.forecast') }}', { method: 'POST', body: data, headers: { 'Accept': 'application/json' } })
                    .then(async r => {
                        const body = await r.json();
                        if (!r.ok) { this.lines = []; this.error = body.message || 'Заповніть форму'; return; }
                        this.error = ''; this.lines = body.lines;
                    })
                    .catch(() => { this.error = 'Прогноз недоступний'; });
            },
        };
    }
</script>
@endpush
