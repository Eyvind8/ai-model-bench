<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AI Model Bench') · AI Model Bench</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <style>
        :root {
            color-scheme: light;
            --surface: #ffffff; --surface-2: #f6f6f7; --border: #e4e4e7;
            --text: #191919; --text-2: #666666;
            --series-1: #257de0;
            --seq-100: #cce3fd; --seq-200: #9dc8f7; --seq-300: #6aabf1; --seq-400: #338ced;
            --seq-500: #2370cd; --seq-600: #1757ac; --seq-700: #0e3d7e;
            --good: #0aac23; --warning: #fbb315; --serious: #f18a59; --critical: #dc3c3d;
        }
        @media (prefers-color-scheme: dark) {
            :root:where(:not([data-theme="light"])) {
                color-scheme: dark;
                --surface: #191919; --surface-2: #222224; --border: #34343a;
                --text: #efefef; --text-2: #9c9c9c;
                --series-1: #338ced;
                --seq-100: #0e3d7e; --seq-200: #0f4a96; --seq-300: #1757ac; --seq-400: #1b64be;
                --seq-500: #2370cd; --seq-600: #338ced; --seq-700: #6aabf1;
            }
        }
        body { background: var(--surface-2); color: var(--text); }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
        .muted { color: var(--text-2); }
        .tbl { width: 100%; border-collapse: collapse; font-size: 14px; }
        .tbl th { text-align: left; font-weight: 600; color: var(--text-2); padding: 8px 10px; border-bottom: 1px solid var(--border); white-space: nowrap; }
        .tbl td { padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
        .tbl tr:last-child td { border-bottom: 0; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; font-size: 14px; font-weight: 500; border: 1px solid var(--border); background: var(--surface); cursor: pointer; }
        .btn:hover { border-color: var(--text-2); }
        .btn-primary { background: var(--series-1); border-color: var(--series-1); color: #fff; }
        .btn-primary:hover { filter: brightness(1.08); border-color: var(--series-1); }
        .btn-danger { color: var(--critical); }
        .input { width: 100%; padding: 7px 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface); color: var(--text); font-size: 14px; }
        .label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; }
        pre.code { background: var(--surface-2); border: 1px solid var(--border); border-radius: 8px; padding: 10px; overflow: auto; max-height: 420px; white-space: pre-wrap; word-break: break-word; }
        a.link { color: var(--series-1); }
        a.link:hover { text-decoration: underline; }
        [x-cloak] { display: none !important; }
    </style>
    @stack('head')
</head>
<body class="min-h-screen antialiased">
<header class="border-b" style="background: var(--surface); border-color: var(--border)">
    <nav class="max-w-7xl mx-auto px-4 h-14 flex items-center gap-1 overflow-x-auto text-sm">
        <a href="{{ route('dashboard') }}" class="font-semibold mr-4 whitespace-nowrap">AI Model Bench</a>
        @foreach ([
            'dashboard' => 'Огляд', 'models.index' => 'Моделі', 'scenarios.index' => 'Сценарії',
            'datasets.index' => 'Датасети', 'playground' => 'Playground', 'runs.index' => 'Прогони', 'arena.index' => 'Арена',
        ] as $route => $label)
            @php($active = request()->routeIs(str_replace('.index', '', $route).'*'))
            <a href="{{ route($route) }}" class="px-3 py-1.5 rounded-md whitespace-nowrap {{ $active ? 'font-semibold' : 'muted' }}"
               @if ($active) style="background: var(--surface-2)" @endif>{{ $label }}</a>
        @endforeach
        <a href="{{ route('runs.create') }}" class="btn btn-primary ml-auto whitespace-nowrap">Новий прогін</a>
    </nav>
</header>

<main class="max-w-7xl mx-auto px-4 py-6 space-y-4">
    @if (session('status'))
        <div class="card px-4 py-3 text-sm" style="border-left: 4px solid var(--good)">✓ {{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="card px-4 py-3 text-sm" style="border-left: 4px solid var(--critical)">✕ {{ session('error') }}</div>
    @endif
    @if (session('warnings'))
        <div class="card px-4 py-3 text-sm space-y-1" style="border-left: 4px solid var(--warning)">
            @foreach (session('warnings') as $warning)<div>⚠ {{ $warning }}</div>@endforeach
        </div>
    @endif
    @if ($errors->any())
        <div class="card px-4 py-3 text-sm space-y-1" style="border-left: 4px solid var(--critical)">
            @foreach ($errors->all() as $error)<div>✕ {{ $error }}</div>@endforeach
        </div>
    @endif

    @yield('content')
</main>
@stack('scripts')
</body>
</html>
