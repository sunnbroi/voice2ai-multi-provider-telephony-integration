@extends('layouts.app')

@section('content')
    <h2>Справочник провайдеров</h2>

    <a href="{{ route('providers.create') }}" class="btn btn-success mb-3">Добавить</a>

    <table class="table">
        <thead><tr><th>Название</th><th>Страна</th><th></th></tr></thead>
        <tbody>
        @foreach($providers as $provider)
            <tr>
                <td>{{ $provider->name }}</td>
                <td><a href="{{ route('providers.edit', $provider) }}" class="btn btn-sm btn-warning">Ред.</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
