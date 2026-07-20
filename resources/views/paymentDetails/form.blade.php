@extends('layouts.app')

@section('content')
    <h2 class="text-center mb-4">
        {{ isset($paymentDetail) ? 'Редактировать' : 'Создать' }} реквизиты
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
              action="{{ isset($paymentDetail) ? route('payment-details.update', $paymentDetail) : route('payment-details.store') }}"
              class="w-100"
              style="max-width: 700px;">
            @csrf
            @if(isset($paymentDetail))
                @method('PUT')
            @endif

            <div class="mb-3">
                <label class="form-label">Страна: </label>
                <span class="fw-bold">{{ $paymentDetail->country }}</span>
            </div>

            <div class="mb-3">
                <label class="form-label">Описание</label>
                <textarea name="description" class="form-control" required>{{ old('description', $paymentDetail->description ?? '') }}</textarea>
            </div>


            <div class="text-center">
                <button type="submit" class="btn btn-primary px-5">Сохранить</button>
            </div>
        </form>
    </div>

@endsection
