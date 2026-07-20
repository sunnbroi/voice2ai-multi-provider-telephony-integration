@extends('layouts.app')

@section('content')
    <h2>Интеграции</h2>
    <div class="row mb-3 align-items-center">
        <div class="col-md-3">
            <form method="GET" class="d-flex">
                <input type="text" name="q" class="form-control me-2" placeholder="Поиск" value="{{ request('q') }}">
                <button class="btn btn-primary">Применить</button>
            </form>
        </div>
        <div class="col-md-3">
            <a href="{{ route('integrations.create') }}" class="btn btn-success">Добавить интеграцию</a>
        </div>
        <div class="col-md-6">
    <form method="POST" action="{{ route('user.updateAdminFields') }}" class="d-flex align-items-center">
        @csrf
        <input type="text" name="admin_bot" placeholder="Админ бот" class="form-control me-2"
               value="{{ auth()->user()->admin_bot }}">
        <input type="text" name="admin_channel" placeholder="Админ канал" class="form-control me-2"
               value="{{ auth()->user()->admin_channel }}">

        <!-- Чекбокс для уведомлений -->
        <div class="form-check me-2">
            <input class="form-check-input" type="checkbox" name="notifications" id="notifications"
                   {{ auth()->user()->notifications ? 'checked' : '' }}>
            <label class="form-check-label" for="notifications">
                Уведомления
            </label>
        </div>

        <!-- Если нужно оставить кнопку Double -->
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</div>

    </div>
    <table class="table table-bordered table-responsive">
        <thead>
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Город</th>
            <th>Страна</th>
            <th>E-mail</th>
            <th>Провайдер</th>
            <th>Теги</th>
            <th>Комм-рий</th>
            <th>Telegram ID</th>
            <th>Ув-ния</th>
            <th>Статус</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @foreach($integrations as $item)
            <tr class="{{$item->is_paid ? 'table-success': ''}}">
                <td>{{ $item->id }}</td>
                <td>{{ $item->title }}</td>
                <td>{{ $item->city }}</td>
                <td>{{ $item->country }}</td>
                <td>
                    <span data-bs-toggle="tooltip"
                          data-bs-placement="top"
                          title="{{ $item->email }}">
                        {{ \Illuminate\Support\Str::limit($item->email, 10) }}
                    </span>
                </td>
                <td>
                    <span data-bs-toggle="tooltip"
                          data-bs-placement="top"
                          title="{{ $item->provider?->name }}">
                        {{ \Illuminate\Support\Str::limit($item->provider?->name, 10) }}
                    </span>
                </td>
                <td style="min-width:200px; line-height: 0.9; word-wrap: break-word; word-break: break-word; white-space: normal;">
                    <span data-bs-toggle="tooltip"
                          data-bs-placement="top"
                          title="{{ preg_replace('/,(\S)/', ', $1', $item->tag) }}"
                          style="font-size: 80%;">
                        {{ \Illuminate\Support\Str::limit(preg_replace('/,(\S)/', ', $1', $item->tag), 70) }}
                    </span>
                </td>
                <td>{{ $item->comment }}</td>
                <td>{{ $item->telegram_chat_id }}</td>
                <td>{{ $item->notify_type }}</td>
                {{--                <td>{{ $item->active ? 'On' : 'Off' }}</td>--}}
                <td>
                    {!! $item->active
                        ? 'On'
                        : 'Off'
                    !!} <br>
                    {!! $item->active
                        ? '<span class="d-inline-block rounded-circle bg-success" style="width: 10px; height: 10px;"></span>'
                        : '<span class="d-inline-block rounded-circle bg-danger" style="width: 10px; height: 10px;"></span>'
                    !!}
                    {!! $item->active_tg_notify_client
                        ? '<span class="d-inline-block rounded-circle bg-success" style="width: 10px; height: 10px;"></span>'
                        : '<span class="d-inline-block rounded-circle bg-danger" style="width: 10px; height: 10px;"></span>'
                    !!}
                    {!! $item->active_tg_notify_admin
                        ? '<span class="d-inline-block rounded-circle bg-success" style="width: 10px; height: 10px;"></span>'
                        : '<span class="d-inline-block rounded-circle bg-danger" style="width: 10px; height: 10px;"></span>'
                    !!}
                </td>
                <td>
                    <a href="{{ route('integrations.edit', $item) }}" class="btn btn-sm btn-warning">Редактировать</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $integrations->appends(request()->query())->links() }}

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(el => new bootstrap.Tooltip(el));
        });
    </script>
@endsection
