@extends('layouts.app')

@section('content')
        <h2>Список звонков</h2>

        <div class="filters mb-3">
            <form method="GET" class="mb-3">
                <div class="row row-cols-1 row-cols-md-6 g-2 align-items-start">
                    <div class="col">
                        <input type="text" name="date_range" class="form-control datepicker-range" placeholder="Дата" value="{{ $filters['date_range'] ?? '' }}">
                    </div>
                    <div class="col">
                        <select name="integration_title" class="form-control select2" data-placeholder="Название">
                            @if(!empty($filters['integration_title']))
                                <option value="{{ $filters['integration_title'] }}" selected>{{ $filters['integration_title'] }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col">
                        <select name="city" class="form-control select2" data-placeholder="Город">
                            @if(!empty($filters['city']))
                                <option value="{{ $filters['city'] }}" selected>{{ $filters['city'] }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col">
                        <select name="country" class="form-control select2" data-placeholder="Страна">
                            @if(!empty($filters['country']))
                                <option value="{{ $filters['country'] }}" selected>{{ $filters['country'] }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col">
                        <select name="provider" class="form-control select2" data-placeholder="Провайдер">
                            @if(!empty($filters['provider']))
                                <option value="{{ $filters['provider'] }}" selected>{{ $filters['provider'] }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col">
                        <select name="phone" class="form-control select2" data-placeholder="Телефон">
                            @if(!empty($filters['phone']))
                                <option value="{{ $filters['phone'] }}" selected>{{ $filters['phone'] }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col">
                        <button type="button"
                                id="starred-toggle"
                                class="btn {{ ($filters['starred'] ?? false) ? 'btn-warning' : 'btn-outline-warning' }} w-100 d-flex justify-content-center align-items-center">
                            <span class="me-1">Отмеченные:</span>
                            <span>★</span>
                            <span id="starred-count" class="ms-1">{{ $starredCount ?? 0 }}</span>
                            <input type="checkbox" id="filter-starred" name="starred" value="1" class="d-none" {{ ($filters['starred'] ?? false) ? 'checked' : '' }}>
                        </button>
                    </div>
                    <div class="col">
                        <select name="tag" class="form-control select2" data-placeholder="Тег">
                            @if(!empty($filters['tag']))
                                <option value="{{ $filters['tag'] }}" selected>{{ $filters['tag'] }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col">
                        <select name="comment" class="form-control select2" data-placeholder="Комментарий">
                            @if(!empty($filters['comment']))
                                <option value="{{ $filters['comment'] }}" selected>{{ $filters['comment'] }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col">
                        <select name="direction" class="form-control">
                            <option value="">Направление</option>
                            <option value="in" {{ ($filters['direction'] ?? '') == 'in' ? 'selected' : '' }}>Входящий</option>
                            <option value="out" {{ ($filters['direction'] ?? '') == 'out' ? 'selected' : '' }}>Исходящий</option>
                        </select>
                    </div>
                    <div class="col">
                        <select name="status" class="form-control">
                            <option value="">Статус</option>
                            <option value="answered" {{ ($filters['status'] ?? '') == 'answered' ? 'selected' : '' }}>Отвечен</option>
                            <option value="missed" {{ ($filters['status'] ?? '') == 'missed' ? 'selected' : '' }}>Пропущен</option>
                        </select>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-auto">
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary">Применить</button>
                            <a href="{{ url()->current() }}" class="btn btn-secondary">Обновить стр.</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle table-striped" id="calls-table">

        <thead>
            <tr>
                <th> </th>
                <th>Время</th>
                <th>Город</th>
                <th>Страна</th>
                <th>Теги</th>
                <th style="width: 150px;">Телефон / Компания</th>
                <th>Запись</th>
                <th>Комментарий</th>
            </tr>
            </thead>
            <tbody id="calls-body">
            @foreach($calls as $call)
                <tr data-id="{{ $call->id }}" class="{{ $call->listened ? 'table-success' : '' }}">
                    <td class="text-center">
                        <button id="star-{{ $call->id }}"
                                class="star-button {{ $call->starred ? 'starred' : '' }}"
                                onclick="toggleStar({{ $call->id }})"
                                type="button"
                                aria-label="Избранное">
                            ★
                        </button>
                    </td>

                    <td>
                        <span data-bs-toggle="tooltip" title="{{ $call->full_call_time }}">
                            {{ $call->formatted_call_time }}
                        </span>
                    </td>
                    <td>{{ $call->integration->city }}</td>
                    <td>{{ $call->integration->country }}</td>
                    <td style="max-width: 200px;line-height: 0.9; word-wrap: break-word; word-break: break-word; white-space: normal;">
                    <span data-bs-toggle="tooltip"
                          data-bs-placement="top"
                          title="{{ preg_replace('/,(\S)/', ', $1', $call->integration->tag) }}"
                          style="font-size: 80%;">
                        {{ \Illuminate\Support\Str::limit(preg_replace('/,(\S)/', ', $1', $call->integration->tag), 70) }}
                    </span>
                    </td>
                    <td style="width: 150px;">
                        <span>{{ $call->direction === 'in' ? $call->from_phone : $call->to_phone }}</span>

                        @php
                            $arrow = $call->direction === 'in' ? '→' : '←';
                            $arrowColor = $call->status === 'answered' ? 'text-primary' : 'text-danger';
                        @endphp

                        <span class="{{ $arrowColor }} mx-1">{{ $arrow }}</span>

                        <span
                            style="cursor: pointer"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            title="номер: {{ $call->direction === 'in' ? $call->to_phone : $call->from_phone }}; оператор: {{ $call->operator_name }}"
                        >
                            {{ $call->integration->title }}
                        </span>
                    </td>
                    <td>
                        @if($call->recording_status === 'uploading')
                            <span class="text-warning">Обрабатывается</span>
                        @elseif($call->recording_status === 'uploaded')
                            @if($call->recording_url)
                                <div class="d-flex align-items-center" data-call-id="{{ $call->id }}">
                                    <button class="btn btn-outline-primary btn-sm me-2 play-btn">▶️</button>
                                    <div class="flex-grow-1">
                                        <input type="range" class="form-range progress-bar" value="0" min="0" step="1">
                                        <div class="d-flex justify-content-between">
                                            <small class="current-time">0:00</small>
                                            <small class="duration">0:00</small>
                                        </div>
                                    </div>
                                    <audio src="https://{{env('PROXY_RECORD_DOMAIN')}}/listen/{{$call->id}}/{{basename($call->recording_url)}}" preload="metadata"></audio>
                                </div>

                            @endif
                        @else
                            <span class="text-danger">Нет записи</span>
                        @endif
                    </td>
                    <td>{{ $call->integration->comment }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        {{ $calls->appends(request()->query())->links() }}
@endsection

@section('scripts')
    <script>

        document.addEventListener('DOMContentLoaded', function () {
        const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(el => new bootstrap.Tooltip(el));

        document.querySelectorAll('tr[data-id]').forEach(row => {
        const container = row.querySelector('[data-call-id]');
        if (!container) return;

        const callId = container.dataset.callId;
        const audio = container.querySelector('audio');
        const playBtn = container.querySelector('.play-btn');
        const progressBar = container.querySelector('.progress-bar');
        const currentTimeEl = container.querySelector('.current-time');
        const durationEl = container.querySelector('.duration');

        function formatTime(sec) {
        const m = Math.floor(sec / 60);
        const s = Math.floor(sec % 60);
        return `${m}:${s < 10 ? '0' + s : s}`;
    }

        audio.addEventListener('loadedmetadata', () => {
        progressBar.max = Math.floor(audio.duration);
        durationEl.textContent = formatTime(audio.duration);
    });

        audio.addEventListener('timeupdate', () => {
        progressBar.value = Math.floor(audio.currentTime);
        currentTimeEl.textContent = formatTime(audio.currentTime);
    });

        progressBar.addEventListener('input', () => {
        audio.currentTime = progressBar.value;
    });

        playBtn.addEventListener('click', () => {
        // останавливаем другие
        document.querySelectorAll('audio').forEach(a => {
        if (a !== audio) {
        a.pause();
        a.closest('[data-call-id]').querySelector('.play-btn').textContent = '▶️';
    }
    });

        if (audio.paused) {
        audio.play();
        playBtn.textContent = '⏸️';
        markListened(callId);
    } else {
        audio.pause();
        playBtn.textContent = '▶️';
    }
    });

        audio.addEventListener('ended', () => {
        playBtn.textContent = '▶️';
    });
    });
    });
    function markListened(callId) {
            fetch(`/calls/mark-listened/${callId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            }).then(response => {
                if (response.ok) {
                    const row = document.querySelector(`tr[data-id='${callId}']`);
                    if (row) row.classList.add('table-success');
                }
            });
        }
        function toggleStar(callId) {
            fetch(`/calls/toggle-star/${callId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            }).then(response => {
                if (response.ok) {
                    response.json().then(data => {
                        const btn = document.querySelector(`#star-${callId}`);
                        if (btn) {
                            btn.classList.toggle('starred', data.starred);
                        }
                    });
                }
            });
        }
        $('.select2').select2({
            theme: 'bootstrap-5',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: '/calls/filter-options',
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        field: $(this).attr('name'),
                        q: params.term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.map(item => ({
                            id: item,
                            text: item
                        }))
                    };
                }
            }
        });
        document.addEventListener('DOMContentLoaded', function () {
            flatpickr('.datepicker-range', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                locale: 'ru',
            });
        });
        document.addEventListener('DOMContentLoaded', function () {
            const btn = document.getElementById('starred-toggle');
            const checkbox = document.getElementById('filter-starred');

            btn.addEventListener('click', function () {
                checkbox.checked = !checkbox.checked;

                btn.classList.toggle('btn-warning', checkbox.checked);
                btn.classList.toggle('btn-outline-warning', !checkbox.checked);
            });

        });
    </script>

@endsection
