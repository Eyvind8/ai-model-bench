@extends('layouts.app')
@section('title', 'Новий прогін')
@section('content')
    <h1 class="text-xl font-semibold">Новий прогін</h1>
    @include('runs._form', ['adhoc' => false])
@endsection
