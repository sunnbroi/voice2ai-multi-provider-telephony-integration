@extends('layouts.app')

@section('content')
    <div class="col-md-6 m-3">
        <form method="POST" action="{{ route('settings.update', \App\Models\Setting::TELEGRAM_PAYMENT_ADMIN_ID_SETTING_ID) }}" class="d-flex align-items-center">
            @csrf
            @method('PUT')
            <label class="col-md-4">ID для Telegram Оповещений</label>
            <div class="col-md-4">
                <input type="text" name="value" class="form-control"
                       value="{{ $telegram_payment_admin_id }}">
            </div>
            <div class="col-md-4 ps-4">
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>



    <h2>Тарифы</h2>
    <table class="table table-bordered table-responsive">
        <thead>
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Цена RU</th>
            <th>Цена UA</th>
            <th>Цена KZ</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @foreach($tariffs as $item)
            <tr>
                <td>{{ $item->id }}</td>
                <td>{{ $item->name }}</td>
                <td>{{ $item->price_ru }}</td>
                <td>{{ $item->price_ua }}</td>
                <td>{{ $item->price_kz }}</td>
                <td>
                    <a href="{{ route('tariffs.edit', $item) }}" class="btn btn-sm btn-warning">Редактировать</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

@endsection
