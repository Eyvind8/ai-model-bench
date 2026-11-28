@extends('layouts.app')
@section('title', 'Playground')
@section('content')
    <div>
        <h1 class="text-xl font-semibold">Playground</h1>
        <p class="text-sm muted">Один текст - кілька моделей, відповіді поруч. Кожен запуск зберігається як прогін.</p>
    </div>
    @include('runs._form', ['adhoc' => true])
@endsection
