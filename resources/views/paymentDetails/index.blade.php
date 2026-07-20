@extends('layouts.app')

@section('content')
    <h2>Реквизиты</h2>
    <table class="table table-bordered table-responsive">
        <thead>
        <tr>
            <th>ID</th>
            <th>Страна</th>
            <th>Описание</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @foreach($paymentDetails as $item)
            <tr>
                <td>{{ $item->id }}</td>
                <td>{{ $item->country }}</td>
                <td>{{ $item->description }}</td>
                <td>
                    <a href="{{ route('payment-details.edit', $item) }}" class="btn btn-sm btn-warning">Редактировать</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

@endsection
