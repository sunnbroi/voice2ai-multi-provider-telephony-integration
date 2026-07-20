@extends('layouts.app')

@section('content')
    <h2 class="text-center mb-4">
        {{ isset($tariff) ? 'Редактировать' : 'Создать' }} тариф
    </h2>

    @if($errors->any())
        <div class="d-flex justify-content-center mb-4">
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="d-flex justify-content-center">
        <form method="POST"
              action="{{ isset($tariff) ? route('tariffs.update', $tariff) : route('tariffs.store') }}"
              class="w-100"
              style="max-width: 700px;">
            @csrf
            @if(isset($tariff))
                @method('PUT')
            @endif

            <div class="mb-3">
                <label class="form-label">Название: </label>
                <input name="name" class="form-control" value="{{ old('name', $tariff->name ?? '') }}"
                       required autocomplete="off">
            </div>

            <div class="mb-3">
                <label class="form-label">Цена RU: </label>
                <input name="price_ru" class="form-control" value="{{ old('price_ru', $tariff->price_ru ?? '') }}"
                       required autocomplete="off">
            </div>

            <div class="mb-3">
                <label class="form-label">Цена UA: </label>
                <input name="price_ua" class="form-control" value="{{ old('price_ua', $tariff->price_ua ?? '') }}"
                       required autocomplete="off">
            </div>

            <div class="mb-3">
                <label class="form-label">Цена KZ: </label>
                <input name="price_kz" class="form-control" value="{{ old('price_kz', $tariff->price_kz ?? '') }}"
                       required autocomplete="off">
            </div>


            <div class="text-center">
                <button type="submit" class="btn btn-primary px-5">Сохранить</button>
            </div>
        </form>
    </div>

@endsection
