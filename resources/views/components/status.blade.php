@props(['value'])
@php
    $value = $value instanceof \BackedEnum ? $value->value : (string) $value;
    [$color, $icon] = match ($value) {
        'ok', 'done', 'yes' => ['var(--good)', '✓'],
        'pending', 'running' => ['var(--series-1)', '…'],
        'rate_limited', 'manual', 'cancelled' => ['var(--serious)', '!'],
        'error', 'failed', 'no' => ['var(--critical)', '✕'],
        default => ['var(--text-2)', '•'],
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs font-medium whitespace-nowrap']) }}>
    <span style="color: {{ $color }}">{{ $icon }}</span>{{ str_replace('_', ' ', $value) }}
</span>
