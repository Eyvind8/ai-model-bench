@extends('layouts.app')
@section('title', 'Прогони')
@section('content')
    <h1 class="text-xl font-semibold">Прогони</h1>
    <x-runs-table :runs="$runs" :counts="$counts" />
    {{ $runs->links() }}
@endsection
