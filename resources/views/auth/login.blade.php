<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }
        .login-box {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 0 16px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
<div class="login-box">
    <h2 class="text-center mb-4">Вход в админку</h2>

    @if($errors->any())
        <div class="alert alert-danger text-center">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Пароль</label>
            <input name="password" type="password" class="form-control" required>
        </div>

        <button class="btn btn-primary w-100">Войти</button>
    </form>
</div>
</body>
</html>
