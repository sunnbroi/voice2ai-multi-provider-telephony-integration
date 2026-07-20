<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function telegramUpdates(Request $request, TelegramService $telegramService)
    {
        $update = json_decode($request->getContent(), true);
        $telegramService->checkUpdate($update);
    }
}
