{{-- Side-by-side answers: one block per input, one column per model, all attempts. --}}
<section class="space-y-2">
    <h2 class="text-lg font-semibold">Відповіді</h2>
    @foreach ($matrix->rows as $i => $row)
        <details class="card" @if ($i === 0 || count($matrix->rows) <= 3) open @endif>
            <summary class="px-4 py-3 cursor-pointer flex flex-wrap items-center gap-3">
                <span class="font-medium">{{ $row->label }}</span>
                @if ($row->expectedScore !== null)<span class="text-xs muted">еталон {{ $row->expectedScore }}</span>@endif
            </summary>
            <div class="px-4 pb-4 space-y-3">
                <div class="text-sm muted max-h-24 overflow-y-auto whitespace-pre-line">{{ \Illuminate\Support\Str::limit($row->input, 600) }}</div>
                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(260px, 1fr))">
                    @foreach ($matrix->models as $model)
                        <div class="rounded-lg p-3 space-y-2" style="border: 1px solid var(--border)">
                            <div class="mono text-xs font-semibold break-all">{{ $model->ref() }}</div>
                            @forelse ($row->results($model->id) as $result)
                                @php
                                    $score = $matrix->scoreField ? ($result->parsed_json[$matrix->scoreField] ?? null) : null;
                                    $text = $matrix->textField ? ($result->parsed_json[$matrix->textField] ?? null) : null;
                                @endphp
                                <div class="text-sm space-y-1 @if (! $loop->first) pt-2 @endif" @if (! $loop->first) style="border-top: 1px dashed var(--border)" @endif>
                                    <div class="flex items-center gap-2 text-xs">
                                        <x-status :value="$result->status" />
                                        @if ($run->repeats > 1)<span class="muted">#{{ $result->attempt_no }}</span>@endif
                                        @if ($score !== null)<span class="font-semibold">оцінка {{ $score }}</span>@endif
                                        @if ($result->latency_ms)<span class="muted">{{ $result->latency_ms }} мс</span>@endif
                                        <a class="link ml-auto" href="{{ route('results.show', $result) }}">деталі</a>
                                    </div>
                                    @if ($result->status->value === 'ok')
                                        <div class="whitespace-pre-line">{{ \Illuminate\Support\Str::limit(is_string($text) ? $text : (string) $result->content, 700) }}</div>
                                    @endif
                                    @if ($result->error_message)
                                        <div class="text-xs" style="color: {{ $result->status->value === 'ok' ? 'var(--serious)' : 'var(--critical)' }}">{{ \Illuminate\Support\Str::limit($result->error_message, 200) }}</div>
                                    @endif
                                </div>
                            @empty
                                <div class="text-xs muted">немає</div>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </div>
        </details>
    @endforeach
</section>
