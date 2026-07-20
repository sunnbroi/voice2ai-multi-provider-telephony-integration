@extends('layouts.app')

@section('content')
    <h2>{{ isset($integration) ? 'Редактировать' : 'Создать' }} интеграцию</h2>

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

    <form method="POST" action="{{ isset($integration) ? route('integrations.update', $integration) : route('integrations.store') }}">
        @csrf
        @if(isset($integration)) @method('PUT') @endif

        <div class="mb-3">
            <label class="form-label">Название</label>
            <input name="title" class="form-control" value="{{ old('title', $integration->title ?? '') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Город</label>
            <input name="city" class="form-control" value="{{ old('city', $integration->city ?? '') }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Страна</label>
            <input name="country" class="form-control" value="{{ old('country', $integration->country ?? '') }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Провайдер</label>
            <select name="provider_id" class="form-control">
                <option value="">-- Не выбран --</option>
                @foreach($providers as $provider)
                    <option value="{{ $provider->id }}" {{ old('provider_id', $integration->provider_id ?? '') == $provider->id ? 'selected' : '' }}>
                        {{ $provider->name }} {{ $provider->country ? "({$provider->country})" : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">API Key</label>
            <input name="api_key" class="form-control" value="{{ old('api_key', $integration->api_key ?? '') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Company ID</label>
            <input name="company_id" class="form-control" value="{{ old('company_id', $integration->company_id ?? '') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Secret</label>
            <input name="secret" class="form-control" value="{{ old('secret', $integration->secret ?? '') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Telegram Chat ID</label>
            <input name="telegram_chat_id" class="form-control" value="{{ old('telegram_chat_id', $integration->telegram_chat_id ?? '') }}">
        </div>
        <div class="mb-3">
            <label class="form-label">Уведомления</label>
            <select name="notify_type" class="form-control">
                <option value="all" {{ old('notify_type', $integration->notify_type ?? '') == 'all' ? 'selected' : '' }}>Все звонки</option>
                <option value="missed" {{ old('notify_type', $integration->notify_type ?? '') == 'missed' ? 'selected' : '' }}>Только пропущенные</option>
            </select>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="active" value="1" {{ (old('active', $integration->active ?? true)) ? 'checked' : '' }}>
            <label class="form-check-label">Активна</label>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="active_tg_notify_client" value="1" {{ (old('active', $integration->active_tg_notify_client ?? true)) ? 'checked' : '' }}>
            <label class="form-check-label">Отправлять в клиентский канал</label>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="active_tg_notify_admin" value="1" {{ (old('active', $integration->active_tg_notify_admin ?? true)) ? 'checked' : '' }}>
            <label class="form-check-label">Отправлять в админ канал</label>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
@endsection
