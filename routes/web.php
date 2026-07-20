<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\PaymentDetailController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TariffController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use App\Models\Call;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

Route::domain(env('APP_DOMAIN'))->group(function () {
    Route::get('/l/{call}/{filename}', function (Call $call, $filename) {
        if (!$call->recording_url) {
            abort(404, 'Нет записи');
        }

        $actualFilename = basename($call->recording_url);
        if ($filename !== $actualFilename) {
            abort(404, 'Имя файла не совпадает');
        }

        $filePath = storage_path('app/public/' . str_replace('/storage/', '', $call->recording_url));

        if (!file_exists($filePath)) {
            abort(404, 'Файл не найден');
        }

        return Response::file($filePath, [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="' . $actualFilename . '"',
        ]);
    })->name('record.listen');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware(['auth'])->group(function () {
        Route::get('/', function () {
            return redirect()->route('calls.index');
        });
        Route::post('/user/update-admin-fields', [UserController::class, 'updateAdminFields'])->name('user.updateAdminFields');
        Route::resource('integrations', IntegrationController::class);
        Route::get('calls', [CallController::class, 'index'])->name('calls.index');
        Route::post('calls/mark-listened/{call}', [CallController::class, 'markListened'])->name('calls.listened');
        Route::post('calls/toggle-star/{call}', [CallController::class, 'toggleStar'])->name('calls.star');

        Route::get('calls/filter-options', [CallController::class, 'autocomplete']);

        Route::resource('payment-details', PaymentDetailController::class);
        Route::resource('tariffs', TariffController::class);
        Route::resource('settings', SettingController::class);
    });

    Route::get('/logout', function () {
        Auth::logout();
        return redirect('/login');
    })->name('logout');
});

Route::post('tg-upd-webhook', [WebhookController::class, 'telegramUpdates']);
