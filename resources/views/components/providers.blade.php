@props(['providers'])
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ($providers as $p)
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div class="font-semibold">{{ $p['name'] }}</div>
                <x-status :value="$p['configured'] ? 'ok' : 'no'" />
            </div>
            <div class="mono muted mt-1">{{ $p['code'] }}</div>
            <div class="text-sm mt-2">Увімкнено моделей: <b>{{ $p['enabled'] }}</b> з {{ $p['total'] }}</div>
            @unless ($p['configured'])
                <div class="text-xs mt-1" style="color: var(--critical)">{{ $p['reason'] }}</div>
            @endunless
        </div>
    @endforeach
</div>
