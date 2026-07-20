<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Control Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css">
    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
    @yield('head')
    <style>
        .star-button {
            background: transparent;
            border: none;
            font-size: 1.0rem;
            color: #ccc;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .star-button:hover {
            color: #ff9900;
        }

        .star-button.starred {
            color: #ff9900;
        }
        audio {
            width: 190px;
            max-width: 300px;
            height: 40px; /* иногда помогает показать расширенный интерфейс */
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
    <div class="container">

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
                aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Переключить навигацию">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="{{ route('calls.index') }}">Звонки</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('integrations.index') }}">Интеграции</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('payment-details.index') }}">Реквизиты</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('tariffs.index') }}">Тарифы</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('logout') }}">Выйти</a></li>
            </ul>
        </div>
    </div>
</nav>

<main class="px-0 px-md-3">
    @yield('content')
</main>
@yield('scripts')
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</html>
